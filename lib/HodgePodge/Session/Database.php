<?php
namespace HodgePodge\Session;

use HodgePodge\Core\Query;
use HodgePodge\Exception;

class Database implements \SessionHandlerInterface
{
    public static $tablename = 'session';
    protected $dbconnection;
    protected $memcached;

    protected $expiry;
    protected $initialRead;
    protected $session_broken = false;

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
        if (!$this->session_broken)
        {
            try {
                $query = new Query($this->dbconnection);
                $query->transaction();
                $query->sql("
                    SELECT sessiondata, expiry FROM `".static::$tablename."` WHERE id = '".$query->escape($id)."' FOR UPDATE
                ");
                list(list($row)) = $query->execute();
            }
            catch (Exception\Query $e)
            {
                $this->session_broken = true;
                throw new Exception\Session('READ', $e);
            }
            
            // If not expired, get sessiondata
            if ($row['expiry'] > time()) {
                $data = $row['sessiondata'];
            }
            return $data;
        }
    }
    
    public function write($id, $data)
    {
        if (!$this->session_broken)
        {
            try {
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
            catch (\HodgePodge\Exception\Query $e)
            {
                $this->session_broken = true;
                throw new Exception\Session('WRITE', $e);
            }
        }
    }
    
    public function destroy($id)
    {
        if (!$this->session_broken)
        {
            try {
                $query = new Query($this->dbconnection);
                $query->sql("
                    Delete from `".static::$tablename."` where id = '".$query->escape($id)."'
                ");
                $query->execute();
                $query->commit();
            }
            catch (\HodgePodge\Exception\Query $e)
            {
                $this->session_broken = true;
                throw new Exception\Session('DESTROY', $e);
            }
        }
    }
    
    public function gc($maxlifetime)
    {
        if (!$this->session_broken)
        {
            try {
                $query = new Query($this->dbconnection);
                $query->sql("
                    Delete from `".static::$tablename."` where expiry < '".time()."'
                ");
                $query->execute();
            }
            catch (\HodgePodge\Exception\Query $e)
            {
                $this->session_broken = true;
                throw new Exception\Session('GC', $e);
            }
        }
    }
}
