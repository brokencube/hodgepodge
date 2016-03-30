<?php
namespace HodgePodge\Core;

use HodgePodge\Exception;
use HodgePodge\Common\SqlString;

class Query implements \Psr\Log\LoggerAwareInterface
{
    protected $name;     // Name of the connection
    protected $pdo;      // Connection object
    
    protected $sql = []; // Array of SQL queries to run
    protected $lock = false;
    protected $debug;

    protected $logger;
    public function setLogger(\Psr\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    
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
        if (!$this->pdo = Database::autoconnect($connection_name)) {
            throw new Exception\Database('CONNECTION_NOT_DEFINED', "Database connection '$name' does not exist");
        }
        $this->name = $connection_name;
        if ($sql) $this->sql($sql);
        
        // Default Logger
        $this->setLogger(Log::get());
    }
    
    // Add arbitary SQL to the query queue
    public function sql($sql, $data = [])
    {
        if ($sql instanceof SQL) {
            $this->sql[] = $sql;
        } else {
            $this->sql[] = new SQL(trim($sql), $data);    
        }
        
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
    
    ////////////////
    
    public function insertId($position = 0)
    {
        return $this->debug[$position]['insert_id'];
    }
    
    public function affectedRows($position = 0)
    {
        return $this->debug[$position]['affected_rows'];
    }
    
    /////////////////
    
    public function escape($string)
    {
        return $this->pdo->quote($string);
    }
    
    public function transaction()
    {
        $this->pdo->beginTransaction();
    }
    
    public function commit()
    {
        $this->pdo->commit();
    }
    
    public function execute()
    {
        // We are only allowed to execute each Query object once!
        if ($this->lock) throw new Exception\Database('QUERY_LOCKED', "This query has already been executed", $this);
        $this->lock = true;
        
        $count = 0;
        $result = [];
        
        try {
            foreach($this->sql as $query) {
                $time = microtime(true);
                $result = $query->execute($this->pdo);
                if ($result->columnCount()) {
                    $return[$count] = $result->fetchAll(\PDO::FETCH_ASSOC);    
                } else {
                    $return[$count] = [];
                }
                
                // Store some useful data about this set of results
                $this->debug[$count]['insert_id'] = $this->pdo->lastInsertId();
                $this->debug[$count]['affected_rows'] = $result->rowCount();
                $this->debug[$count]['time'] = microtime(true) - $time;
                $count++;
            }
        }
        catch (\PDOException $e) {
            $this->debug[$count]['insert_id'] = $this->pdo->lastInsertId();
            $this->debug[$count]['affected_rows'] = $result->rowCount();
            $this->debug[$count]['time'] = microtime(true) - $time;
            
            throw new Exception\Query($this, $e);
        }
        finally {
            // Log the query with Psr3 Logger
            $this->logQuery($this);
        }
        
        // Finally, return the results of the query
        return $return;
    }
    
    protected function createQuery($table_alias, $data, $type, $where = array(), QueryOptions $options = null)
    {
        list($table, $alias) = explode(' ', $table_alias);
        $prepared_data = [];
        
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
                        $part = $this->createQueryPart($j_alias ?: $j_table, $column, $value, true);
                        $where_array[] = $part[0];
                        $prepared_data = array_merge($prepared_data, array_slice($part, 1));
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
                $part = $this->createQueryPart($alias ?: $table, $column, $value);
                $data_array[] = $part[0];
                $prepared_data = array_merge($prepared_data, array_slice($part, 1));
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
                    $part = $this->createQueryPart($alias ?: $table, $column, $value, true);
                    $where_array[] = $part[0];
                    $prepared_data = array_merge($prepared_data, array_slice($part, 1));
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
                $part = $this->createQueryPart($alias ?: $table, $column, $value);
                $update_array[] = $part[0];
                $prepared_data = array_merge($prepared_data, array_slice($part, 1));
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
        return new SQL($query, $prepared_data);
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
            preg_match('/^([!=<>]*)([^!=<>]+)([!=<>]*)$/', $column, $parts);
            $prefix = $parts[1] ?: $parts[3];
            $column = $parts[2];
            
            preg_match('/^`(.+?)`\.`?(.+?)`?$/', $column, $fullyqualified);
            if (!$fullyqualified[1])
            {
                $column = "`$table`.`$column`";
            }
            
            // Special cases for null values in where clause
            if ($prefix == '!' and is_null($value)) return ["$column is not null"];
            if (is_null($value)) return ["$column is null"];
            
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
        else
        {
            $column = "`$table`.`$column`";
        }
        
        $col = "$column $comparitor ";
        switch (true) {
            case $value instanceof SqlString:
                return [$col . (string) $value];
            
            case is_array($value):
                $count = count($value);
                $in_clause = '(' . implode(',', array_fill(0, $count, '?')) . ')';
                $mapped_strings = array_map(function ($a) { return (string) $a; }, $value);
                if ($prefix == '!')
                {
                    return array_merge(["$column not in $in_clause"], $mapped_strings);
                }
                else  
                {
                    return array_merge(["$column in $in_clause"], $mapped_strings);
                }
            
            case is_object($value):
                return [$col . '?', (string) $value];
            
            default:
                return [$col . '?', $value];
        }
    }

    public function logQuery(Query $query)
    {
        if ($this->disabled) return;
        
        $count = 0;
        foreach($query->sql as $sql)
        {
            $preview = Log::format(substr($sql->sql,0,100),true);
            $time = number_format($query->debug[0]['time'] * 1000, 2);
            
            $message = "{$time}ms Con:{$query->name} | $preview";
            $this->logger->notice(
                $message,
                [
                    'query' => $sql->sql,
                    'data' => $sql->data,
                    'debug' => $query->debug[$count]
                ]
            );
        }
    }
}