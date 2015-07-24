<?php
namespace HodgePodge\Session;

use HodgePodge\Core\Query;

class Database implements \SessionHandlerInterface
{
    public static $tablename = 'session';
    protected $dbconnection;
    protected $memcached;

    protected $expiry;
    protected $initialRead;

    public function __construct($expiry = 2592000) // 60*60*24*30
    {
        $this->expiry = $expiry;
    }
    
    public function open($dbconnection, $sessionName)
    {
        // Store name of db connection to use (if needed)
        $this->dbconnection = $dbconnection;
    }
    
    public function close()
    {
        // Blank memcached variable to GC Memcached client
        $this->memcached = null;
        $this->dbconnection = null;
    }
    
    public function read($id)
    {
        $query = new Query($this->dbconnection);
        $query->transaction();
        $query->sql("
            SELECT sessiondata, expiry FROM `".static::$tablename."` WHERE id = '".$query->escape($id)."'
        ");
        list(list($row)) = $query->execute();
        
        // If not expired, get sessiondata
        if ($row['expiry'] > time()) {
            $data = $row['sessiondata'];
        }
        return $data;
    }
    
    public function write($id, $data)
    {
        $query = new Query($this->dbconnection);
        $query->sql("
            REPLACE INTO `".static::$tablename."` SET
                id = '".$query->escape($id)."',
                sessiondata = '".$query->escape($data)."',
                expiry = '".(time() + $this->expiry)."'
        ");
        $query->execute();
        $query->commit();
    }
    
    public function destroy($id)
    {
        $query = new Query($this->dbconnection);
        $query->sql("
            Delete from `".static::$tablename."` where id = '".$query->escape($id)."'
        ");
        $query->execute();
        $query->commit();
    }
    
    public function gc($maxlifetime)
    {
        $query = new Query($this->dbconnection);
        $query->sql("
            Delete from `".static::$tablename."` where expiry < '".time()."'
        ");
        $query->execute();
    }
}
