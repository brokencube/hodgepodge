<?php
namespace LibNik\Orm;

use LibNik\Common;
use LibNik\Core;
use LibNik\Exception;

class Data
{
    protected $data = array();     // Data from columns on this table
    protected $external = array(); // Links to foreign key objects
    protected $database;           // Database this data is associated with
    protected $schema;             // Schema object for this database
    protected $table;              // Class this data is associated with
    protected $model;              // Fragment of Schema object for this table
    protected $locked = true;      // Can we use __set() - for updates/inserts
    protected $new = false;        // Is this to be a new row? (used with Model::new_db())
    
    public function __construct(array $data, $table, Schema $schema, $locked = true, $new = false)
    {
        $this->database = $schema->database;
        $this->table = $table;
        $this->schema = $schema;
        $this->model = $schema->getTable($table);
        $this->locked = $locked;
        $this->new = $new;
        
        // Pull in data from $data
        foreach($data as $key => $value) {
            // Make a special object for dates
            if(($this->model['columns'][$key] == 'datetime' or $this->model['columns'][$key] == 'timestamp') and !is_null($value)) {
                $this->data[$key] = new Time($value, new \DateTimeZone('UTC'));
            } else {
                $this->data[$key] = $value;
            }
        }
    }
    
    // Generally used when this class is accessed through $modelobject->db()
    // This returns an 'unlocked' version of this object that can be used to modify the database row.
    public function __clone()
    {
        $this->locked = false;
        $this->external = array();
    }

    // Create a open cloned copy of this object, ready to reinsert as a new row.
    public function duplicate()
    {
        $clone = clone $this;
        $clone->new = true;
        unset($clone->data['id']);
        return $clone;
    }
    
    public function lock()
    {
        $this->locked = true;
        return $this;
    }
    
    // Accessor method for object properties (columns from the db)
    public function &__get($var)
    {
        /* This property is a native database column, return it */
        if (isset($this->data[$var])) return $this->data[$var];
        
        /* This property has already been defined, return it */
        if (isset($this->external[$var])) return $this->external[$var];
        
        /* This property hasn't been defined, so it's not one of the table columns. We want to look at foreign keys and pivots */
        
        /* If we try and access a foreign key column without adding the _id on the end assume we want the object, not the id
         * From example at the top: $proj->account_id returns 1      $proj->account returns Account object with id 1
         */
        
        if (key_exists($var, (array) $this->model['one-to-one'])) {        
            /* Call Tablename::factory(foreign key id) to get the object we want */
            $table = $this->model['one-to-one'][$var];
            $id = $this->data['id'];
            $this->external[$var] = Model::factoryObjectCache($id, $table, $this->database);
            
            return $this->external[$var];
        }
        
        if (key_exists($var, (array) $this->model['many-to-one'])) {        
            /* Call Tablename::factory(foreign key id) to get the object we want */
            $table = $this->model['many-to-one'][$var];
            $id = $this->data[$var . '_id'];
            $this->external[$var] = Model::factoryObjectCache($id, $table, $this->database);
            
            return $this->external[$var];
        }
        
        /* Look for lists of objects in other tables referencing this one */
        if (key_exists($var, (array) $this->model['one-to-many'])) {
            // If this Model_Data isn't linked to the db yet, then linked values cannot exist
            if (!$id = $this->data['id']) return array();
            
            $table = $this->model['one-to-many'][$var]['table'];
            $column = $this->model['one-to-many'][$var]['column_name'];
            
            // Use the model factory to find the relevant items
            $this->external[$var] = Model::factory(array($column => $id), $table, $this->database);
            
            return $this->external[$var];
        }
        
        /* Search $model['many-to-many'] to see if an appropriate pivot string has been defined */
        if (key_exists($var, (array) $this->model['many-to-many'])) {
            // If this Model_Data isn't linked to the db yet, then linked values cannot exist
            if (!$this->data['id']) return array();
            
            // Get pivot schema
            $pivot = $this->model['many-to-many'][$var];
            
            // Get a list of ids linked to this object (i.e. the tablename_id stored in the pivot table)
            $pivot_schema = $this->schema->getTable($pivot['pivot']);
            $pivot_tablename = $pivot_schema['table_name'];
            
            list($raw) = Core\Query::run("Select `{$pivot['column']}` as id from `{$pivot_tablename}` where `{$pivot['id']}` = {$this->data['id']}", $this->database);    
            
            // Rearrange the list of ids into a flat array
            foreach($raw as $raw_id) $id[] = $raw_id['id'];
            
            // Use the model factory to retrieve the objects from the list of idsd
            $this->external[$var] = Model::factory(array('id' => $id), $pivot['table'], $this->database);
            
            return $this->external[$var];
        }
    }

    public function __isset($var)
    {
        // Is it already set in local array?
        if (isset($this->data[$var])) return true;
        if (isset($this->external[$var])) return true;
        
        // Check through all the possible foreign keys for a matching name
        if (key_exists($var, (array) $this->model['one-to-one'])) return true;        
        if (key_exists($var, (array) $this->model['many-to-one'])) return true;
        if (key_exists($var, (array) $this->model['one-to-many'])) return true;
        if (key_exists($var, (array) $this->model['many-to-many'])) return true;
        
        return false;
    }
    
    public function __set($var, $value)
    {
        // Cannot change data if it is locked (i.e. it is attached to a Model object)
        if ($this->locked) throw new Exception\Model('MODEL_DATA:SET_WHEN_LOCKED', array($var, $value));
        
        // Cannot update primary key on existing objects
        // (and cannot set id for new objects that don't have a foreign primary key)
        if ($var == 'id' && $this->new == false && $this->model['type'] != 'foreign') {
            throw new Exception\Model('MODEL_DATA:CANNOT_CHANGE_ID', array($var, $value));
        }
        
        // Updating normal columns
        if (key_exists($var, $this->model['columns']))
        {
            if ($this->model['columns'][$var] == 'datetime' or $this->model['columns'][$var] == 'timestamp') {
                // Special checks for datetimes
                if ($value instanceof Time) { // Orm\Time is aware of timezones - preferred
                    $this->data[$var] = $value->mysql();    
                } elseif (($datetime = strtotime($value)) !== false) {// Fall back to standard strings
                    $this->data[$var] = date(MYSQL_DATE, $datetime);
                } else { 
                    // Oops!
                    throw new Exception\Model('MODEL_DATA:DATETIME_VALUE_EXPECTED_FOR_COLUMN', array($var, $value));
                }
            } elseif (is_scalar($value) or is_null($value) or $value instanceof DB_String) {
                // Standard values
                $this->data[$var] = $value;
            } else {
                // Objects, arrays etc that cannot be stored in a db column. Explosion!
                throw new Exception\Model('MODEL_DATA:SCALAR_VALUE_EXPECTED_FOR_COLUMN', array($var, $value));
            }
            
            return;
        }
        
        // table_id -> Table - Foreign keys to other tables
        if (key_exists($var, (array) $this->model['many-to-one'])) {
            if (is_null($value)) {
                $this->data[$var.'_id'] = null;
                $this->external[$var] = null;
                return;
            } elseif ($value instanceof Model) {
                $this->data[$var.'_id'] = $value->id;
                $this->external[$var] = $value;
                return;
            } else {
                throw new Exception\Model('MODEL_DATA:MODEL_EXPECTED_FOR_KEY', array($var, $value));
            }
        }
        
        // Pivot tables - needs an array of appropriate objects for this column
        if (key_exists($var, (array) $this->model['many-to-many'])) {
            if (is_array($value)) $value = new Collection($value);
            if (!$value) $value = new Collection();
            
            // Still not got a valid collection? Boom!
            if (!$value instanceof Collection) throw new Exception\Model('MODEL_DATA:ARRAY_EXPECTED_FOR_PIVOT', array($var, $value));
            
            foreach($value as $obj) {                
                if (!$obj instanceof Model) throw new Exception\Model('MODEL_DATA:MODEL_EXPECTED_IN_PIVOT_ARRAY', array($var, $value, $obj));
            }
            
            $this->external[$var] = $value;
            return;
        }
        
        // Table::this_id -> this - Foreign keys on other tables pointing to this one - we cannot 'set' these here.
        // These values must be changes on their root tables (i.e. the table with the twin many-to-one relationship)
        if (key_exists($var, (array) $this->model['one-to-many'])) {
            throw new Exception\Model('MODEL_DATA:CANNOT_SET_EXTERNAL_KEYS_TO_THIS_TABLE', array($var, $value));
        }
        
        // Undefined column
        throw new Exception\Model('MODEL_DATA:UNEXPECTED_COLUMN_NAME', array($var, $value, $this->model));
    }
    
    public function commit()
    {
        // Create a new query
        $query = new Core\Query($this->database);        
        $this->buildQuery($query);
        $values = $query->execute(true);
        
        // Get the id we just inserted/updated
        if ($this->new) {
            $id = $query->insertId(0);
            $this->new = false;
        } else {
            $id = $this->data['id'];
        }
        
        // Return the id for the object we just created/updated
        return $id;
    }
    
    protected function buildQuery(&$query)
    {
        // [FIXME] [NikB] Why did I split this back out to update/insert rather than replace?
        // Log says "Fixed major overwriting problem in commit()" but what was getting overwritten?
        
        // Insert/Update the data, and store the insert id into a variable
        if ($this->new) {
            $query->insert($this->table, $this->data);
            $query->sql("SELECT last_insert_id() into @id");
        } else {
            $query->update($this->table, $this->data, array('id' => $this->data['id']));
            $query->sql("SELECT ".$this->data['id']." into @id");        
        }
        
        $origin_id = new Common\SqlString('@id');
        
        // Foreign tables
        foreach ($this->external as $table => $value) {
            // Skip property if this isn't an M-M table (M-1 and 1-M tables are dealt with in other ways)
            if (!$pivot = $this->model['many-to-many'][$table]) continue;
            
            // Clear out any existing data for this object - this is safe because we are in an atomic transaction.
            $query->sql("Delete from $table where {$pivot['id']} = @id");
            
            // Loops through the list of objects to link to this table
            foreach ($value as $object) {                    
                $query->insert(
                    $table,                              // Pivot table
                    array(
                        $pivot['id'] => $origin_id,      // Id of this object
                        $pivot['column'] => $object->id  // Id of object linked to this object
                    )
                );
            }
        }
    }
    
    // Get the table that this object is attached to.
    public function getTable()
    {
        return $this->table;
    }
    
    public function getDatabase()
    {
        return $this->database;
    }
}