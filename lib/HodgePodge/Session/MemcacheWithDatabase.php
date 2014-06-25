<?php
namespace HodgePodge\Session;

use HodgePodge\Core\Query;

class MemcacheWithDatabase implements \SessionHandlerInterface
{
    protected $expiry;
    protected $initialRead;
    protected $memcacheMiss = false;
    
    protected $memcached;
    protected $dbconnection;

    public function __construct($expiry = 2592000) // 60*60*24*30
    {
        $this->expiry = $expiry;
    }
    
    public function open($savePath, $sessionName)
    {
        list($server, $port, $dbconnection) = explode(':', $savePath);
        $this->memcached = new \Memcached();
        $this->memcached->addServer($server, $port);
        $this->dbconnection = $dbconnection;
    }
    
    public function close()
    {
        $this->memcached = null;
        $this->dbconnection = null;
    }
    
    public function read($id)
    {
        $data = $this->memcached->get(ini_get('memcached.sess_prefix') . $id);
        
        // No data, try and read from database.
        if (is_null($data))
        {
            $this->memcacheMiss = true;
            $query = new Query($this->dbconnection);
            $query->sql("
                SELECT sessiondata, expiry FROM session WHERE id = '".$query->escape($id)."'
            ");
            list(list($row)) = $query->execute();
            
            // If not expired
            if ($row['expiry'] > time()) {
                $data = $row['sessiondata'];
            }
        }
        
        if (is_null($this->initialRead)) $this->initialRead = $data;
        return $data;
    }
    
    public function write($id, $data)
    {
        $result = $this->memcached->set(ini_get('memcached.sess_prefix') . $id, $data, $this->expiry);
        
        // Write back data to database if updated OR with 1% chance (to keep expiry relatively up-to-date)
        if ($data !== $this->initialRead or rand(0, 100) == 100)
        {
            $this->memcacheMiss = true;
            $query = new Query($this->dbconnection);
            $query->sql("
                REPLACE INTO session SET
                    id = '".$query->escape($id)."',
                    sessiondata = '".$query->escape($data)."',
                    expiry = '".(time() + $this->expiry)."'
            ");
            list($data) = $query->execute();
        }
        
        return $result;
    }
    
    public function destroy($id)
    {
        $result = $this->memcached->delete('session:' . $id);
        $query = new Query($this->dbconnection);
        $query->sql("
            Delete from session where id = '".$query->escape($id)."'
        ");
        $query->execute();
    }
    
    public function gc($maxlifetime)
    {
        $query = new Query($this->dbconnection);
        $query->sql("
            Delete from session where expiry < '".time()."'
        ");
        $query->execute();
    }
}
