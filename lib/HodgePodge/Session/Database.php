<?php
namespace HodgePodge\Session;

use Automatorm\Database\Query;
use HodgePodge\Exception;

class Database implements \SessionHandlerInterface
{
    public static $tablename = 'session';
    protected $dbconnection;
    protected $memcached;

    protected $expiry;
    protected $initialRead;
    protected $session_broken = false;
    protected $write = false;
    protected $lock = false;

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
                
                if ($this->write) {
                    // We know we are writing, so get a "FOR UPDATE" lock
                    if (!$this->lock) {
                        // First time we do this, start a transaction!
                        $query->transaction();
                        $this->lock = true;
                    }
                    $query->sql("SELECT sessiondata, expiry FROM `".static::$tablename."` WHERE id = ? FOR UPDATE", $id);
                } else {
                    // No Lock for Read only!
                    $query->sql("SELECT sessiondata, expiry FROM `".static::$tablename."` WHERE id = ?", $id);    
                }
                list(list($row)) = $query->execute();
            }
            catch (\Automatorm\Exception\Database $e)
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
    
    public function writeLock($id)
    {
        if (!$this->write) {
            $this->write = true;
            // Read again to get "FOR UPDATE" lock on table row
            return $this->read($id);
        }
    }
    
    public function write($id, $data)
    {
        if (!$this->session_broken && $this->write)
        {
            try {
                $query = new Query($this->dbconnection);
                $query->sql("
                    REPLACE INTO `".static::$tablename."` SET
                        id = ?,
                        sessiondata = ?,
                        expiry = ?
                ", [$id, $data, time() + $this->expiry]);
                $query->execute();
                $query->commit();
            }
            catch (\Automatorm\Exception\Database $e)
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
                    Delete from `".static::$tablename."` where id = ?
                ", $id);
                $query->execute();
                
                if ($this->write) {
                    $query->commit();
                    $this->write = false;
                    $this->lock = false;
                }
            }
            catch (\Automatorm\Exception\Database $e)
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
            catch (\Automatorm\Exception\Database $e)
            {
                $this->session_broken = true;
                throw new Exception\Session('GC', $e);
            }
        }
    }
}
