<?php
namespace LibNik\Core;

use LibNik\Exception;

class Database
{
    protected static $connections = array();
    protected static $details = array();
    
    /************************
     * CONNECTION FUNCTIONS *
     ************************/
    public static function register($db, $name = 'default')
    {
        if(!is_array($db)) throw new Exception\Database('NO_DETAILS', 'No database details provided to DB::register()');
        
        self::$details[$name] = $db;
    }

    public static function registerMultiple($dbs)
    {
        foreach($dbs as $name => $db) self::register($db, $name);
    }

    public static function autoconnect($name = 'default')
    {
        global $config;
        
        if (!self::$connections[$name])    {
            if (!$db = self::$details[$name]) throw new Exception\Database('CONNECTION_NOT_DEFINED', "Database connection '$name' not defined in config.");
            
            self::$connections[$name] = new \mysqli($db['server'], $db['user'], $db['pass'], $db['database']);
            self::$connections[$name]->set_charset('utf8');
            
            if ($error = self::$connections[$name]->connect_error) {
                unset(self::$connections[$name]);
                throw new Exception\Database('CONNECTION_FAILED', 'Database connection failed: ' . $error);
            }
        }
        
        return self::$connections[$name];
    }
    
    public static function closeConnection($name = 'default')
    {
        if(self::$connections[$name])
        {
            self::$connections[$name]->close();
            unset(self::$connections[$name]);
        }
    }
    
    // Check that a connection (or all connections) are still up and working
    // Recommend using "ini_set('mysqli.reconnect', 'on');" in while(true) scripts
    public static function ping($name = null)
    {
        $allok = true;
        if (!$name)
        {
            foreach(self::$connections as $conn => $db)
            {
                $allok = self::autoconnect($conn)->ping() & $allok;
            }
        }
        else
        {
            $allok = self::autoconnect($name)->ping();
        }
        return $allok;
    }

    public static function escape($string, $name = 'default')
    {
        return self::autoconnect($name)->real_escape_string($string);
    }
}
