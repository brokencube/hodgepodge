<?php

namespace HodgePodge\Orm;

use HodgePodge\Exception;
use HodgePodge\Core\Query;
use HodgePodge\Core\Cache;
use HodgePodge\Core\Database;

class Schema
{
    const CURRENT_VERSION = 2;
    
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
    protected $version;
    
    protected function __construct($model, $database, $namespace) {
        $this->model = $model;
        $this->database = $database;
        $this->namespace = $namespace;
        $this->version = static::CURRENT_VERSION;
    }
    
    public static function generate($dbconnection = 'default', $namespace = 'models', $cachebust = false)
    {
        Cache::lifetime(60 * 60 * 24 * 7, 'model'); // Cache model weekly
        
        $db = Database::details($dbconnection);
        $cache = new Cache('schema_' . md5($dbconnection . $namespace . $db['database']), 'model');
        
        $obj = $cache();
        
        if ($obj && $obj->version != static::CURRENT_VERSION)
        {
            unset($obj);
        }
        
        if ($cachebust or !$obj) {
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
                // If we have found a table with only foreign keys then this must be a pivot table
                if (count($pivot['many-to-one']) > 1 and count($pivot['columns']) == count($pivot['many-to-one'])) {
                    // Grab all foreign keys and rearrange them into arrays.
                    $tableinfo = array();
                    foreach($pivot['many-to-one'] as $column => $tablename) {
                        $tableinfo[] = array('column' => $column . '_id', 'table' => $tablename);
                    }
                    
                    $property_name = Schema::underscoreCase($pivotname);
                    
                    // For each foreign key, store details in the table it point to on how to get to the OTHER table in the "Many to Many" relationship
                    foreach ($tableinfo as $i => $table)
                    {
                        // Outersect of tables to create an array of all OTHER foreign keys in this table, for this foreign key.
                        $othertables = array_values(array_diff_assoc($tableinfo, array($i => $table)));
                        
                        $model[ $table['table'] ][ 'many-to-many' ][ $property_name ] = array(
                            'pivot' => $pivotname,
                            'connections' => $othertables,
                            'id' => $table['column'],
                        );
                        
                    }
                    
                    $model[$pivotname]['type'] = 'pivot';
                    
                    // Remove the M-1 keys for these tables to fully encapsulate the M-M scheme.
                    foreach ($tableinfo as $table)
                    {
                        foreach((array) $model[ $table['table'] ][ 'one-to-many' ] as $key => $val) {
                            if ($val['table'] == $pivotname) unset ($model[ $table['table'] ][ 'one-to-many' ][$key]);
                        }
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
    
    // Based on supplied data, try and guess the appropriate class and tablename
    public function guessContext($class_or_table)
    {
        // Namespace classname? Remove that namespace before continuing
        if (strrpos($class_or_table, '\\') !== false) {
            $class_or_table = substr($class_or_table, strrpos($class_or_table, '\\') + 1);
        }
        
        // Normalise the (namespace stripped) class or table name so we don't have to worry about under_score or CamelCase
        $normalised = self::normaliseCase($class_or_table);
        
        // First guesses as to table and class names
        $class = $this->namespace . '\\' . self::camelCase($normalised);
        $table = $this->model[$normalised] ? $this->model[$normalised]['table_name'] : '';
        
        // If the guessed classname exists, then we are making one of those objects.
        if (class_exists($class)) {
            // Does this class have a different table, otherwise use guess from above.
            if ($class::$tablename) {
                $normalised_table = self::normaliseCase($class::$tablename);
                $table = $this->model[$normalised_table] ? $this->model[$normalised_table]['table_name'] : '';
            }
        } else {
            // We didn't find an appropriate class - make a Model object using the guessed table name.
            $class = 'HodgePodge\\Orm\\Model';
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
