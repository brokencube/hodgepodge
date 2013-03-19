<?php

namespace LibNik\Orm;

use LibNik\Exception;
use LibNik\Core\Query;
use LibNik\Core\Cache;

class Schema {
    
    public static $object_list = array();
    
    public static function get($dbconnection)
    {
        if (!static::$object_list[$dbconnection]) {
            throw new Exception\Model('NO_GENERATED_SCHEMA', $dbconnection);
        }
        
        return static::$object_list[$dbconnection];
    }
    
    protected $model;
    protected $database;
    protected $namespace;
    
    protected function __construct($model, $database, $namespace) {
        $this->model = $model;
        $this->database = $database;
        $this->namespace = $namespace;
    }
    
    public static function generate($dbconnection = 'default', $namespace = 'models', $cachebust = false)
    {
        Cache::lifetime(60 * 60 * 24 * 7, 'model'); // Cache model weekly
        $cache = new Cache('model', 'schema_' . $dbconnection);
        if ($cachebust or !$obj = $cache()) {
            // Get a list of all foreign keys in this database
            $query = new Query($dbconnection);
            $query->sql("
                SELECT b.table_name, b.column_name, b.referenced_table_name, b.referenced_column_name
                FROM information_schema.table_constraints a 
                JOIN information_schema.key_column_usage b
                ON a.table_schema = b.table_schema AND a.constraint_name = b.constraint_name
                WHERE a.table_schema = database() AND a.constraint_type = 'FOREIGN KEY'
                ORDER BY b.table_name, b.constraint_name;"
            );
            $query->sql("
                SELECT table_name, column_name, data_type FROM information_schema.columns where table_schema = database();
            ");
            
            list($keys, $schema) = $query->execute();
            
            // Assemble list of table columns by table
            foreach ($schema as $row) {
                $table_name = self::normaliseCase($row['table_name']);

                $model[$table_name]['table_name'] = $row['table_name'];
                // All tables default to type 'table' - can also be 'pivot' or 'foreign' as detected later
                $model[$table_name]['type'] = 'table';
                // List all columns for this table
                $model[$table_name]['columns'][$row['column_name']] = $row['data_type'];
            }
            
            // Loop over every foreign key definition
            foreach ($keys as $row) {
                $table_name = self::normaliseCase($row['table_name']);
                $ref_table_name = self::normaliseCase($row['referenced_table_name']);
                
                if ($row['referenced_column_name'] == 'id' and $row['column_name'] == 'id') {
                    // If both columns in the key are 'id' then this is a 1 to 1 relationship.
                    // Create a link in both objects to each other
                    $model[$ref_table_name]['one-to-one'][self::underscoreCase($table_name)] = $table_name;
                    $model[$table_name]['one-to-one'][self::underscoreCase($ref_table_name)] = $ref_table_name;
                    $model[$table_name]['type'] = 'foreign';
                } elseif ($row['referenced_column_name'] == 'id') {
                    // if this foreign key points at one 'id' column then this is a usable foreign 'key'
                    if (substr($row['column_name'], -3) == '_id') {
                        $model[$table_name]['many-to-one'][substr($row['column_name'], 0, -3)] = $ref_table_name;
                        
                        // Add the key constraint in reverse, trying to make a sensible name.
                        if (substr($row['column_name'], 0, -3) == $row['referenced_table_name']) {
                            $property_name = self::underscoreCase($table_name);
                        } else {
                            $property_name = self::underscoreCase($table_name) . '_' . substr($row['column_name'], 0, -3);
                        }
                        $model[$ref_table_name]['one-to-many'][$property_name] = array('table' => $table_name, 'column_name' => $row['column_name']);
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
                        'column' => $table[1]['column'],
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
            
            $obj = new static($model, $dbconnection, $namespace);
            $cache($obj);
        }
        
        return static::$object_list[$dbconnection] = $obj;
    }

    // Normalised an under_scored or CamelCased phrase to "under scored" or "Camel Cased"
    public static function normaliseCase($string)
    {
        return trim(strtolower(preg_replace('/([A-Z])|_/', ' $1', $string)));
    }
    
    public static function camelCase($string)
    {
        return str_replace(' ', '', ucwords(self::normaliseCase($string)));
    }
    
    public static function underscoreCase($string)
    {
        return str_replace(' ', '_', self::normaliseCase($string));
    }
    
    // Based on supplied data, and the current class, figure out what class + db table we should be contructing.
    public function getFactoryContext($class_or_table)
    {        
        // Use the supplied table/class name, or fall back to the name of the current class
        $normalised = self::normaliseCase($class_or_table);
        
        // First guesses as to table and class names
        $class = $this->namespace . '\\' . self::camelCase($normalised);
        $table = $this->model[$normalised] ? $this->model[$normalised]['table_name'] : '';
        
        // If the guessed classname exists, then we are making one of those objects.
        if (class_exists($class)) {
            // Does this class have a different table, otherwise use guess from above.
            if ($class::$tablename) {
                $normalised_table = self::normaliseCase($class::$tablename);
                $table = $this->model[$normalised] ? $this->model[$normalised]['table_name'] : '';
            }
            $table = $class::$tablename ?: $table;
        } else {
            // We didn't find an appropriate class - make a Model object using the guessed table name.
            $class = 'LibNik\\Orm\\Model';
        }
        
        // We haven't found an entry in the schema for our best guess table name? Boom!
        if (!$table) {
            throw new Exception\Model('NO_SCHEMA', array($class_or_table, $normalised, $class, $table));
        }
                
        return array($class, $table);
    }
    
    public function getTable($table)
    {
        $normalised = self::normaliseCase($table);
        
        return $this->model[$normalised];
    }

    public function __get($var)
    {
        return $this->$var;
    }
}
