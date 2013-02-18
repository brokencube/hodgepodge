<?php
namespace LibNik\Orm;

use LibNik\Common;
use LibNik\Core;
use LibNik\Exception;

/* MVC Model Class giving a lightweight ORM interface with an indirect active record pattern.
 * The rationale for this superclass is to make it trivial to create an object representing a single row in a database table (and a class
 * representing a database table).
 * 
 * Features:
 * * Auto generation of object properties - TableName::get($id)->column_name syntax
 * * Foreign key support
 * *   - Can create other Model objects of appropriate types based on foreign keys specified.
 * * Many to Many support - Can understand pivot tables for many to many relationships
 *
 * Database Design Caveats:
 * * Pivot tables must only contain 2 columns (the two foreign keys).
 * * All tables (except pivots) must have an "id int primary key auto_increment" column
 * * Foreign key columns must end in '_id'
 */

class Model implements \JsonSerializable
{
    public static $dbconnection = 'default'; // Override database connection associated with this class - for subclasses
    public static $tablename;                // Override table associated with this class - for subclasses
    public static $schema;                   // Database schema, as read in by Model::generate_schema();
    public static $namespace;                // List of namespaces for schemas. Should really be merged into $schema in future!
    protected static $instance;              // An internal store of already created objects so that objects for each row only get created once
    
    /* PUBLIC CONSTRUCTION METHODS */
    public static function get($id, $force_refresh = false)
    {
        return static::factoryObjectCache($id, null, null, $force_refresh);
    }
    
    // Find a single(!) object via an arbitary $where clause
    public static function find($where)
    {
        $o = new Core\QueryOptions();
        return static::factory($where, null, null, $o->limit(1), true);
    }
    
    // Find a collection of objects via an arbitary $where clause
    public static function findAll($where = array(), $limit = null, $offset = 0, $sort = null)
    {
        $o = new Core\QueryOptions();
        return static::factory($where, null, null, $o->limit($limit, $offset)->sort($sort));
    }
    
    /* FACTORY METHODS */    
    // Build an appropriate Model object based on id and class/table name
    // Special case: $limit = -1 :: return the first object without collection wrapper.
    final public static function factory($where, $class_or_table_name = null, $database = null, Core\QueryOptions $options = null, $single_result = false)
    {
        // Determine which db connection to use
        if (!$database) $database = static::$dbconnection;
        
        // Figure out the base class and table we need based on current context
        list($base_class, $table) = static::getFactoryContext($class_or_table_name, $database);
        
        // Get data from database        
        $data = Model::factoryData($where, $table, $database, $options);
                
        // If we're in one object mode, and have no data, return null rather than an empty Model_Collection!
        if ($single_result and !$data) return null;
            
        // New container for the results
        $collection = new Collection();
        
        foreach($data as $row) {
            // Database data object unique to this object
            $data_obj = new Data($row, $table, $database);
            
            // Ask the base_class if there is a subclass we should be using.
            $class = call_user_func(array($base_class, '_subclass'), $data_obj);
            
            // Create the object!!
            $obj = new $class($data_obj);
                        
            // Store it in the object cache.        
            Model::$instance[$database][$table][$row['id']] = $obj;
            
            // Call Model objects _init() function - this is to avoid recursion issues with object's natural constructor and the cache above
            $obj->_init();
            
            // If we only wanted one object then shortcut and return now that we have it!
            if ($single_result) return $obj;
            
            // Add to the model collection
            $collection[] = $obj;
        }
        
        // Return the collection.
        return $collection;
    }
    
    protected static function _subclass(Data $data)
    {
        return get_called_class();
    }
    
    final public static function factoryObjectCache($id, $class_or_table = null, $database = null, $force_refresh = false)
    {
        // Invalid id? No object :P
        if (!is_numeric($id)) return null;
        if (!$database) $database = static::$dbconnection;
        
        if (!$force_refresh) {
            // Check Model object cache
            list($class, $table) = static::getFactoryContext($class_or_table, $database);        
            if (isset(Model::$instance[$database][$table][$id])) {
                return Model::$instance[$database][$table][$id];
            }
        }
        
        /* Cache miss, so create new object */
        $o = new Core\QueryOptions();
        return static::factory(array('id' => $id), $class_or_table, $database, $o->limit(1), true);
    }
    
    // Get data from database from which we can construct Model objects
    final protected static function factoryData($where, $table, $database, Core\QueryOptions $options = null)
    {        
        // Check we have an appropriate schema for this table
        if (!$model = Model::$schema[$database][$table]) throw new Exception\Model('NO_SCHEMA', array($database, $where, $table));
        
        // Select * from $table where $where
        $query = new Core\Query($database);
        list($data) = $query->select($table, $where, $options)->execute();
                
        return $data;
    }
    
    // Based on supplied data, and the current class, figure out what class + db table we should be contructing.
    final protected static function getFactoryContext($class_or_table, $database)
    {
        // Retrieve the model namespace for the database we are using
        $namespace = Model::$namespace[$database];
        
        // Use the supplied table/class name, or fall back to the name of the current class
        $class_or_table = strtolower($class_or_table ?: get_called_class());
        
        // Guess the table name -- $class_or_table will either be:
        // A) The table name (use as is)
        // B) The (global) class name (assume class is named same as table)
        // or C) The namespaced class name (strip namespace and use as above)
        if (strrpos($class_or_table, '\\') !== false) {
            $table = strtolower(substr($class_or_table, strrpos($class_or_table, '\\') + 1));
        } else {
            $table = strtolower($class_or_table);
        }
        
        // Use the stripped table name + namespace to guess a classname.
        // Table names are lowercase+underscores, Classnames are camelcase
        $class = $namespace . '\\' . str_replace(' ', '', ucwords(str_replace('_', ' ', $table)));
        
        // If the guessed classname exists, then we are making one of those objects.
        if (class_exists($class)) {
            // Does this class have a different table, otherwise base it on the class name
            $return = array($class, $class::$tablename ?: $table);
        } else {
            // We didn't find an appropriate class - make a Model object using the guessed Table name.
            $return = array('LibNik\\Orm\\Model', $table);
        }
        
        return $return;
    }
    
    // Return an empty Model_Data object for this class/table so that a new object can be constructed (and a new row entered in the table).
    // For 'foreign' tables, a parent object must be supplied.
    public static function newData(Model $parent_object = null)
    {
        // Get the schema for the current class/table
        $database = static::$dbconnection;
        list($class, $table) = static::getFactoryContext(null, $database);
        if (!Model::$schema[$database][$table]) throw new Exception\Model('NO_SCHEMA', array($database, $class, $table, static::$tablename));
        
        // Make a new blank data object
        $model_data = new Data(array(), $table, $database, false, true);
        
        // "Foreign" tables use a "parent" table for their primary key. We need that parent object for it's id.
        if (Model::$schema[$database][$table]['type'] == 'foreign') {
            if (!$parent_object) throw new Exception\Model('NO_PARENT_OBJECT', array($database, $class, $table, static::$tablename));
            $model_data->id = $parent_object->id;
        }
        
        return $model_data;
    }
    
    
    ///////////////////////////////////
    /*        OBJECT METHODS         */
    ///////////////////////////////////
    
    protected $id;        // Id of the table row this object represents
    protected $_data;     // Container for the Model_Data object for this row. Used for both internal and external __get access.
    protected $database;  // Name of db connection relating to this object - useful for extending these objects.
    protected $table;     // Name of db table relating to this object - useful for extending these objects.
    protected $model;     // Reference to the Model::$schema[$table] array for this object's table
    protected $cache;     // Retain $_db the next time this item is serialised.
    
    // This is a replacement constructor that is called after the model object has been placed in the instance cache.
    // The real constructor is marked final as the normal constructor can cause infinite loops when combined with Class::get();
    // Empty by default - designed to be overridden by subclass
    protected function _init() {}
    
    // Actual constructor - stores row data and a the $model for this object type.
    final protected function __construct(Data $data)
    {
        // Together the table and id identify a unique row in the database
        $this->_data = $data;
        $this->id = $data->id;
        $this->table = $data->getTable();
        $this->database = $data->getDatabase();
        
        // &reference so that we don't accidentally _copy_ this data into each object    
        $this->model = &Model::$schema[$this->database][$this->table];        
    }
    
    // [FIXME] Is it actually safe to return ids for all objects, or do we want to even obfuscate this?
    public function jsonSerialize()
    {
        return ['id' => $this->id];
    }
    
    // Called after we pull the object out of the session/cache (during the session_start() call, for example)
    public function __wakeup()
    {
        if (!$this->cache) {
            // Get the data about this object out of the database again (to make sure it's up to date)
            list($data) = Model::factoryData(array('id' => $this->id), $this->table, $this->database); 
            
            // Database data object unique to this object
            $this->_data = new Data($data, $this->table, $this->database);            
        }
        
        // &reference so that we don't accidentally _copy_ this data into each object
        // [Note] PHP should handle this automatically - perhaps I am being unjustifiably cautious...
        $this->model = &Model::$schema[$this->database][$this->table];
        
        // Store the object in the object cache
        Model::$instance[$this->database][$this->table][strtolower(get_called_class())][$this->id] = $this;
        
        // Call replacement constructor after storing in the cache list (to prevent recursion)
        $this->_init();
        
        return $this;
    }
        
    // Because we usually reconstruct the object from the db when it leaves the session,
    // we only need to keep the id and table/db to fully "rehydrate" the object.
    // If we are caching the object then keep the Model_Data object for this model.
    // [Note] Because we are not saving $cache, it will revert to null when the object is pulled out of the cache.
    //        This is intentional to stop the object becoming stale if it moves from the cache and into the session, for example.
    
    public function __sleep()
    {
        $properties = array('id', 'table', 'database');
        if ($this->cache) {
            $properties[] = '_data';
        }
        return $properties;
    }
    
    // Dynamic object properties - Prefer properties set on the model object over column data from the db (Model_Data object)
    public function __get($var)
    {        
        // If the property actually exists, then return it rather than looking at the Model_Data object.
        if (property_exists($this, $var)) return $this->{$var};
        
        // If a special property method exists, then call it (again, instead of looking at the Model_Data object).
        if (method_exists($this, '_property_'.$var)) return call_user_func(array($this, '_property_'.$var));
        
        // Nothing special set up, default to looking at the Model_Data object.
        return $this->_data->{$var};
    }
    
    final public function data()
    {
        return clone $this->_data;
    }
    
    public function cachable($bool = true)
    {
        $this->cache = $bool;
        return $this;
    }
    
    ////////////////////////////////////
    /* Reverse engineer a database to calculate links between tables */
    public static function generateSchema($dbconnection = null, $namespace = 'models', $override_cache = false)
    {
        if (!$dbconnection) $dbconnection = 'default';
        
        Core\Cache::lifetime(60 * 60 * 24 * 7, 'model'); // Cache model weekly
        $cache = new Core\Cache('model', 'model_' . $dbconnection);
        
        if ($override_cache or !$model = $cache()) {
            // Get a list of all foreign keys in this database
            list($keys, $schema) = Core\Query::run("
                SELECT b.table_name, b.column_name, b.referenced_table_name, b.referenced_column_name
                FROM information_schema.table_constraints a 
                JOIN information_schema.key_column_usage b
                ON a.table_schema = b.table_schema AND a.constraint_name = b.constraint_name
                WHERE a.table_schema = database() AND a.constraint_type = 'FOREIGN KEY'
                ORDER BY b.table_name, b.constraint_name;
                
                SELECT table_name, column_name, data_type FROM information_schema.columns where table_schema = database();
            ", $dbconnection);
            
            // Assemble list of table columns by table
            foreach ($schema as $row) {
                // All tables default to type 'table' - can also be 'pivot' or 'foreign' as detected later
                $model[$row['table_name']]['type'] = 'table';
                // List all columns for this table
                $model[$row['table_name']]['columns'][$row['column_name']] = $row['data_type'];
            }
            
            // Loop over every foreign key definition
            foreach ($keys as $row) {
                if ($row['referenced_column_name'] == 'id' and $row['column_name'] == 'id') {
                    // If both columns in the key are 'id' then this is a 1 to 1 relationship.
                    // Create a link in both objects to each other
                    $model[$row['referenced_table_name']]['one-to-one'][$row['table_name']] = $row['table_name'];
                    $model[$row['table_name']]['one-to-one'][$row['referenced_table_name']] = $row['referenced_table_name'];
                    $model[$row['table_name']]['type'] = 'foreign';
                } elseif ($row['referenced_column_name'] == 'id') {
                    // if this foreign key points at one 'id' column then this is a usable foreign 'key'
                    if (substr($row['column_name'], -3) == '_id') {
                        $model[$row['table_name']]['many-to-one'][substr($row['column_name'], 0, -3)] = $row['referenced_table_name'];
                        
                        // Add the key constraint in reverse, trying to make a sensible name.
                        if (substr($row['column_name'], 0, -3) == $row['referenced_table_name']) {
                            $property_name = $row['table_name'];
                        } else {
                            $property_name = $row['table_name'] . '_' . substr($row['column_name'], 0, -3);
                        }
                        $model[$row['referenced_table_name']]['one-to-many'][$property_name] = array('table' => $row['table_name'], 'column_name' => $row['column_name']);
                    }
                }
            }
            
            // Now look for pivot tables 
            foreach ($model as $pivotname => $pivot) {
                // If we have found a table with only 2 columns which are both foreign keys then this must be a pivot table
                if (count($pivot['many-to-one']) == 2 and count($pivot['columns']) == 2) {
                    // Grab both foreign keys and rearrange them into two arrays.
                    $table = array();
                    foreach($pivot['many-to-one'] as $column => $tablename) {
                        $table[] = array('column' => $column . '_id', 'table' => $tablename);
                    }
                    
                    // For each foreign key, store details in the table it point to on how to get to the OTHER table in the "Many to Many" relationship
                    $model[ $table[0]['table'] ][ 'many-to-many' ][ $pivotname ] = array(
                        'pivot' => $pivotname,
                        'column' => $table[1]['column'] ,
                        'table' => $table[1]['table'],
                        'id' => $table[0]['column'],
                    );
                    
                    $model[ $table[1]['table'] ][ 'many-to-many' ][ $pivotname ] = array(
                        'pivot' => $pivotname,
                        'column' => $table[0]['column'],
                        'table' => $table[0]['table'],
                        'id' => $table[1]['column'],
                    );
                    
                    $model[$pivotname]['type'] = 'pivot';
                    
                    // Remove the M-1 keys for this table to encapsulate the M-M scheme.
                    foreach((array) $model[ $table[0]['table'] ][ 'one-to-many' ] as $key => $val) {
                        if ($val['table'] == $pivotname) unset ($model[ $table[0]['table'] ][ 'one-to-many' ][$key]);
                    }
                    
                    foreach((array) $model[ $table[1]['table'] ][ 'one-to-many' ] as $key => $val) {
                        if ($val['table'] == $pivotname) unset ($model[ $table[1]['table'] ][ 'one-to-many' ][$key]);
                    }
                }
            }
            
            $cache($model);
        }
        
        Model::$namespace[$dbconnection] = $namespace;
        return Model::$schema[$dbconnection] = $model;
    }
}
