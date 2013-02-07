<?php
namespace LibNik\Core;

use LibNik\Exception;
use LibNik\Orm;

class Query
{
    protected $name;    // Name of the connection
    protected $mysql;     // Connection object
    
    protected $sql = array(); // Array of SQL queries to run
    protected $lock = false;
    
    protected $debug;
        
    // Readonly access to object properties
    public function __get($var) {
        switch($var) {
            case 'name':
            case 'mysql':
            case 'sql':
            case 'debug':
            case 'lock':
                return $this->{$var};
        }
        return $this->debug[$var];
    } 
    
    // Static version of constructor for oneliners    
    static public function create($connection_name = 'default', $sql = null)
    {
        return new Query($connection_name, $sql);    
    }
    
    static public function run($sql, $connection_name = 'default')
    {
        return Query::create($connection_name, $sql)->execute();
    }
    
    // Create a new query container
    public function __construct($connection_name = 'default', $sql = null)
    {
        if (!$this->mysql = DB::autoconnect($connection_name)) {
            throw new Exception\Database('CONNECTION_NOT_DEFINED', "Database connection '$name' does not exist");
        }
        $this->name = $connection_name;
        if ($sql) $this->sql($sql);
    }
    
    // Add arbitary SQL to the query queue
    public function sql($sql)
    {
        $this->sql[] = trim($sql);        
        return $this;
    }
    
    /* Helper functions for simple queries */
    public function insert($table, $array)
    {
        return $this->sql($this->createQuery($table, $array, 'insert'));
    }
    
    public function update($table, $array, $where)
    {
        return $this->sql($this->createQuery($table, $array, 'update', $where));
    }
    
    public function select($table, $where = array(), QueryOptions $options = null)
    {
        return $this->sql($this->createQuery($table, null, 'select', $where, $options));
    }
    
    public function replace($table, $array, $id_column = null)
    {
        return $this->sql($this->createQuery($table, $array, 'replace', $id_column));
    }
    
    public function count($table, $where = array())
    {
        return $this->sql($this->createQuery($table, null, 'count', $where));
    }
    
    public function insertId($position = 0)
    {
        return $this->debug['insert_id'][$position];
    }
    
    public function affectedRows($position = 0)
    {
        return $this->debug['affected_rows'][$position];
    }
    
    /////////////////
    
    public function execute($add_transaction = false)
    {
        // We are only allowed to execute each Query object once!
        if ($this->lock) throw new Exception\Database('QUERY_LOCKED', "This query has already been executed", $this);
        $this->lock = true;
        
        // Start timing query
        $total_time = microtime(true);
        
        // Wrap the queries in a transaction block.
        if ($add_transaction) {
            array_unshift($this->sql, 'START TRANSACTION;');
            array_push($this->sql, 'COMMIT;');
        }
        
        // Concatenate all the queries together for multi_query function
        $query = implode("\n", $this->sql);
        
        $mysql = $this->mysql;
        
        foreach($this->sql as $query) {
            // Do the database query
            if ($mysql->multi_query($query)) {
                $count = 0;
                do {
                    $return[$count] = array();
                    
                    // If we have a result set, collated it into an array of rows
                    if ($result = $mysql->store_result()) {
                        while($row = $result->fetch_array(MYSQLI_ASSOC)) {
                            $return[$count][] = $row;
                        }
                        
                        // We don't need that funky result resource anymore...
                        $result->close();
                    }
                    
                    // Store some useful data about this set of results
                    $this->debug['insert_id'][$count] = $mysql->insert_id;
                    $this->debug['affected_rows'][$count] = $mysql->affected_rows;
                    $count++;
                    
                } while($mysql->next_result());
            }            
        }
        
        // Stop timing query
        $this->debug['total_time'] = microtime(true) - $total_time;
        $this->debug['count'] = $count;
        
        if ($mysql->warning_count) {            
            $e = $mysql->get_warnings(); 
            do { 
                $this->debug['warnings'][] = "{$e->errno}: {$e->message}\n"; 
            } while ($e->next());                 
        }
        
        if ($mysql->error) {
            $this->debug['error'] = "{$mysql->errno}: {$mysql->error}\n";
        }                        
        
        // Log the query with Log::
        Log::get()->log_query($this);
        
        // If we had an error and are using exceptions, throw one.
        if ($this->debug['error']) {
            throw new Exception\Query($this);
        }
        
        // If we wrapped this query in a transaction, remove the debug data about those statements.
        if ($add_transaction) {
            // Remove blank entries for START TRANSACTION; and COMMIT;
            array_shift($return);
            array_shift($this->debug['insert_id']);
            array_shift($this->debug['affected_rows']);
            
            array_pop($return);
            array_pop($this->debug['insert_id']);
            array_pop($this->debug['affected_rows']);
        }
        
        // Finally, return the results of the query
        return $return;
    }
    
    protected function createQuery($table, $data, $type, $where = array(), QueryOptions $options = null)
    {
        switch($type) {            
            // Queries that need building
            case 'count':
                $query = 'SELECT count(*) as count FROM ' . $table;
                break;
            
            case 'select':
                $query = 'SELECT * FROM ' . $table;
                break;
            
            case 'update':
                $query = 'UPDATE ' . $table;
                break;
            
            case 'insert':
                $query = 'INSERT INTO ' . $table;
                break;
            
            case 'replace':
                $query = 'INSERT INTO ' . $table;
                $id_column = $where;
                unset($where);
                break;
        }
        
        // Column updates
        if ($type != 'select' and $type != 'count') {
            if (!$data) throw new Exception\Database('No column data given to create_query', $this);
            foreach ($data as $column => $value) {
                $data_array[] = $this->createQueryPart($column, $value);
            }
            $query .= " SET " . implode(",\n", $data_array);
        }
        
        // Where clause
        if ($where) {
            if ($where instanceof SqlString) {
                $query .= " WHERE " . (string) $where;
            } else {
                foreach ($where as $column => $value) {
                    if ($value instanceof SqlString) {
                        $where_array[] = (string) $value;
                    } else {
                        $where_array[] = $this->createQueryPart($column, $value);    
                    }
                }
                $query .= " WHERE " . implode("\n AND ", $where_array);                
            }
        }
        
        // Update clause for REPLACE
        if ($type == 'replace') {
            // Doing a nasty MySQL trick here to keep last_insert_id consistant: see http://dev.mysql.com/doc/refman/5.0/en/insert-on-duplicate.html
            $data[$id_column] = DB::sql("LAST_INSERT_ID($id_column)");            
            foreach ($data as $column => $value) {
                $update_array[] = $this->createQueryPart($column, $value);
            }
            $query .= " ON DUPLICATE KEY UPDATE " . implode(",\n", $update_array);
        }
        
        if ($options && $options->sort) {
            $query .= " ORDER BY {$options->sort}";
        }
        
        if ($options && $options->limit) {
            $query .= " LIMIT {$options->offset},{$options->limit}";
        }
        
        $query .= ";";
        return $query;
    }
    
    function createQueryPart($column, $value)
    {
        $col = "`$column` = ";
        switch (true) {
            case $value instanceof SqlString:
                return $col . (string) $value;
            
            case $value instanceof Orm\Time:
                return $col . "'" . $value->mysql() . "'";
            
            case is_int($value) || is_float($value): 
                return $col . $value;
            
            case is_null($value):
                return $col . 'null';
            
            case is_array($value):
                foreach($value as $var) {
                    switch(true) {
                        case is_int($var) || is_float($var):
                            $in[] = (string) $var;
                            break;
                        
                        case is_string($var):
                            $in[] = "'".  DB::escape($var, $this->name). "'";
                            break;
                    }
                }
                return "`$column` in (" . implode(", ", $in) . ")";
            
            default:
                return $col . "'" . DB::escape((string) $value, $this->name) . "'";
        }
        return $part;
    }
}
