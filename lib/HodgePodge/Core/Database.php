<?php
namespace HodgePodge\Core;

use HodgePodge\Exception;

class Database
{
    protected static $connections = array();
    protected static $details = array();
    
    /************************
     * CONNECTION FUNCTIONS *
     ************************/
    public static function register($db, $name = 'default')
    {
        if (!is_array($db))
            throw new Exception\Database('NO_DETAILS', 'No database details provided to DB::register()');
        
        self::$details[$name] = new static($db, $name);
    }

    public static function get($name = 'default')
    {
        return self::$details[$name];
    }
    
    public function __get($var)
    {
        if (property_exists($this, $var)) return $this->{$var};
        return null;
    }
    
    protected $name;
    protected $type;
    protected $user;
    protected $pass;
    protected $server;
    protected $database;
    protected $connection;
    
    protected function __construct($details, $name = 'default')
    {
        $this->name = $name;
        $this->server = $details['server'] ?: 'localhost';
        $this->user = $details['user'] ?: 'root';
        $this->pass = $details['pass'] ?: '';
        $this->database = $details['database'] ?: 'test';
        $this->type = $details['type'] ?: 'mysql';
    }
    
    public function connect()
    {
        unset($this->connection);
        
        $dsn = $this->type . ':host=' . $this->server . ';dbname=' . $this->database.';charset=utf8';
        try {
            $this->connection = new \PDO($dsn, $this->user, $this->pass, [
                \PDO::ATTR_PERSISTENT => true,
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
            ]);
        } catch (\PDOException $e) {
            unset ($this->connection);
            throw new Exception\Database('CONNECTION_FAILED', "Database connection failed ({$dsn})", null, $e);
        }
        
        return $this->connection;
    }
    
    public static function autoconnect($name = 'default')
    {
        if (!$db = static::get($name)) throw new Exception\Database('CONNECTION_NOT_DEFINED', "Database connection '$name' not defined in config.");
        return $db->connection ?: $db->connect();
    }

    public static function escape($string, $name = 'default')
    {
        return self::autoconnect($name)->quote($string);
    }
}
