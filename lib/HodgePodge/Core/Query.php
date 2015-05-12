<?php
namespace HodgePodge\Core;

use HodgePodge\Exception;
use HodgePodge\Common\SqlString;

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
        if (!$this->mysql = Database::autoconnect($connection_name)) {
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
    
    public function select($table, $where = array(), QueryOptions $options = null, $columns = '*')
    {
        return $this->sql($this->createQuery($table, $columns, 'select', $where, $options));
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
    
    public function escape($string)
    {
        return $this->mysql->real_escape_string($string);
    }
    
    public function execute($transaction = false)
    {
        // We are only allowed to execute each Query object once!
        if ($this->lock) throw new Exception\Database('QUERY_LOCKED', "This query has already been executed", $this);
        $this->lock = true;
        
        // Start timing query
        $total_time = microtime(true);
        
        // Set transaction mode
        $this->mysql->autocommit(!$transaction);
                
        $count = 0;
        foreach($this->sql as $query) {
            // Do the database query
            $this->mysql->real_query($query);
            $return[$count] = array();
            
            // If we have a result set, collated it into an array of rows
            if ($result = $this->mysql->store_result()) {
                while($row = $result->fetch_array(MYSQLI_ASSOC)) {
                    $return[$count][] = $row;
                }            
                #$return[$count] = $result->fetch_all(MYSQLI_ASSOC);
                // We don't need that funky result resource anymore...
                $result->close();
            }
            
            // Store some useful data about this set of results
            $this->debug['insert_id'][$count] = $this->mysql->insert_id;
            $this->debug['affected_rows'][$count] = $this->mysql->affected_rows;
        
            // Check for any warning from the last statement
            if ($this->mysql->warning_count) {
                $e = $this->mysql->get_warnings();
                do {
                    $this->debug['warnings'][$count][] = "{$e->errno}: {$e->message}\n";
                } while ($e->next());
            }
            
            // Check for any errors from the last statement
            if ($this->mysql->error) {
                $this->debug['error'] = "{$this->mysql->errno}: {$this->mysql->error}\n";
            }
            
            $count++;
        }
        
        // Stop timing query
        $this->debug['total_time'] = microtime(true) - $total_time;
        $this->debug['count'] = $count;

        // Log the query with Log::
        Log::get()->logQuery($this);

        // If we had an error, throw and exception.
        if ($this->debug['error']) {
            if ($transaction) $this->mysql->rollback();
            throw new Exception\Query($this);
        }
        
        if ($transaction) $this->mysql->commit();
                
        // Finally, return the results of the query
        return $return;
    }
    
    protected function createQuery($table_alias, $data, $type, $where = array(), QueryOptions $options = null)
    {
        list($table, $alias) = explode(' ', $table_alias);
        
        switch($type) {
            // Queries that need building
            case 'count':
                $query = "SELECT count(*) as count FROM `{$table}`" . ($alias ? " as `{$alias}`" : '');
                break;
            
            case 'select':
                $query = "SELECT $data FROM `{$table}`" . ($alias ? " as `{$alias}`" : '');
                break;
            
            case 'update':
                $query = "UPDATE `{$table}`" . ($alias ? " as `{$alias}`" : '');
                break;
            
            case 'insert':
                $query = "INSERT INTO `{$table}`" . ($alias ? " as `{$alias}`" : '');
                break;
            
            case 'replace':
                $query = "INSERT INTO `{$table}`" . ($alias ? " as `{$alias}`" : '');
                $id_column = $where;
                unset($where);
                break;
        }
        
        if ($options && $options->join)
        {
            foreach ($options->join as $join)
            {
                list($j_table, $j_alias) = explode(' ', $join['table']);
                $query .= " JOIN `{$j_table}`" . ($j_alias ? " as `{$j_alias}`" : '');
                $where_array = [];                
                if ($join['where'] instanceof SqlString) {
                    $query .= " ON " . (string) $join['where'];
                } else {
                    foreach ($join['where'] as $column => $value) {
                        $where_array[] = $this->createQueryPart($j_alias ?: $j_table, $column, $value, true);
                    }
                    $query .= " ON " . implode("\n AND ", $where_array);
                }
            }
        }
        
        // Column updates
        if ($type != 'select' and $type != 'count') {
            $data_array = [];
            if (!$data) throw new Exception\Database('NO_COLUMN_DATA', 'No column data given to create_query', $this);
            foreach ($data as $column => $value) {
                $data_array[] = $this->createQueryPart($alias ?: $table, $column, $value);
            }
            $query .= " SET " . implode(",\n", $data_array);
        }
        
        // Where clause
        if ($where) {
            $where_array = [];
            if ($where instanceof SqlString) {
                $query .= " WHERE " . (string) $where;
            } else {
                foreach ($where as $column => $value) {
                    $where_array[] = $this->createQueryPart($alias ?: $table, $column, $value, true);
                }
                $query .= " WHERE " . implode("\n AND ", $where_array);
            }
        }
        
        // Update clause for REPLACE
        if ($type == 'replace') {
            $update_array = [];
            // Doing a nasty MySQL trick here to keep last_insert_id consistant: see http://dev.mysql.com/doc/refman/5.0/en/insert-on-duplicate.html
            $data[$id_column] = new SqlString("LAST_INSERT_ID($id_column)");
            foreach ($data as $column => $value) {
                $update_array[] = $this->createQueryPart($alias ?: $table, $column, $value);
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
    
    protected function createQueryPart($table, $column, $value, $where = false)
    {
        if (is_int($column) and $value instanceof SqlString)
        {
            return (string) $value;
        }
        
        $comparitor = '=';
        if ($where)
        {
            preg_match('/^([^a-zA-Z0-9]*)[a-zA-Z0-9]/', $column, $prefix);
            $prefix = $prefix[1];
            
            // Strip any prefix off the front of the column name
            $column = substr($column, strlen($prefix));
            
            // Special cases for null values in where clause
            if ($prefix == '!' and is_null($value)) return "`$table`.`$column` is not null";
            if (is_null($value)) return "`$table`.`$column` is null";
            
            switch ($prefix) {
                case '=': $comparitor = '='; break;
                case '!': $comparitor = '!='; break;
                case '!=': $comparitor = '!='; break;
                case '>': $comparitor = '>'; break;
                case '<': $comparitor = '<'; break;
                case '>=': $comparitor = '>='; break;
                case '<=': $comparitor = '<='; break;
            }
        }
        
        $col = "`$table`.`$column` $comparitor ";
        switch (true) {
            case $value instanceof SqlString:
                return $col . (string) $value;
            
            case is_int($value) || is_bool($value):
                return $col . intval($value);
            
            case is_float($value):
                return $col . $value;
            
            case is_null($value):
                return $col . 'null';
            
            case is_array($value):
                if ($prefix == '!')
                {
                    if (empty($value)) return 'true';
                    foreach($value as $var) {
                        switch(true) {
                            case is_int($var) || is_float($var):
                                $in[] = (string) $var;
                                break;
                            
                            case is_string($var):
                                $in[] = "'".  Database::escape($var, $this->name). "'";
                                break;
                        }
                    }
                    return "`$column` not in (" . implode(", ", $in) . ")";
                }
                else
                {
                    if (empty($value)) return 'false';
                    foreach($value as $var) {
                        switch(true) {
                            case is_int($var) || is_float($var):
                                $in[] = (string) $var;
                                break;
                            
                            case is_string($var):
                                $in[] = "'".  Database::escape($var, $this->name). "'";
                                break;
                        }
                    }
                    return "`$column` in (" . implode(", ", $in) . ")";
                }
            
            default:
                return $col . "'" . Database::escape((string) $value, $this->name) . "'";
        }
        return $part;
    }
}