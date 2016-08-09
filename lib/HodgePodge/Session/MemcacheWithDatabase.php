<?php
namespace HodgePodge\Session;

use Automatorm\Database\Query;
use HodgePodge\Exception;

class MemcacheWithDatabase implements \SessionHandlerInterface
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
    
    public function open($savePath, $sessionName)
    {
        // eg: localhost:11211:session
        list($server, $port, $dbconnection) = explode(':', $savePath);
        
        // Create a memcached client instance ready to read.
        $this->memcached = new \Memcached();
        $this->memcached->addServer($server, $port);
        
        // Store name of db connection to use (if needed)
        $this->dbconnection = $dbconnection;
        return true;
    }
    
    public function close()
    {
        // Blank memcached variable to GC Memcached client
        $this->memcached = null;
        $this->dbconnection = null;
        return true;
    }
    
    public function read($id)
    {
        // Use ini session prefix so that sessions are compatible with builtin 'memcached' handler
        $data = $this->memcached->get(ini_get('memcached.sess_prefix') . $id);
        
        // No data, try and read from database.
        if (is_null($data))
        {
            $query = new Query($this->dbconnection);
            $query->sql("
                SELECT sessiondata, expiry FROM `".static::$tablename."` WHERE id = '".$query->escape($id)."'
            ");
            list(list($row)) = $query->execute();
            
            // If not expired, get sessiondata
            if ($row['expiry'] > time()) {
                $data = $row['sessiondata'];
            }
        }
        
        // First time we call this, remember what the initial content was
        // We will use this later to decide whether to skip updating the database.
        if (is_null($this->initialRead)) $this->initialRead = $data;
        return $data;
    }
    
    public function write($id, $data)
    {
        // Set data back to memcached (to keep expiry up to date, as well as to fix cache misses).
        $result = $this->memcached->set(ini_get('memcached.sess_prefix') . $id, $data, $this->expiry);
        
        // Write back data to database if updated OR with 1% chance (to keep expiry time relatively up-to-date)
        if ($data !== $this->initialRead or rand(0, 100) == 100)
        {
            $query = new Query($this->dbconnection);
            $query->sql("
                REPLACE INTO `".static::$tablename."` SET
                    id = '".$query->escape($id)."',
                    sessiondata = '".$query->escape($data)."',
                    expiry = '".(time() + $this->expiry)."'
            ");
            $query->execute();
        }
        
        return $result;
    }
    
    public function destroy($id)
    {
        // Delete session from memcached and database
        $result = $this->memcached->delete('session:' . $id);
        $query = new Query($this->dbconnection);
        $query->sql("
            Delete from `".static::$tablename."` where id = '".$query->escape($id)."'
        ");
        $query->execute();
        return true;
    }
    
    public function gc($maxlifetime)
    {
        // Memcached deals with it's own GC, but we have to do it for the DB.
        $query = new Query($this->dbconnection);
        $query->sql("
            Delete from `".static::$tablename."` where expiry < '".time()."'
        ");
        $query->execute();
        return true;
    }
}
