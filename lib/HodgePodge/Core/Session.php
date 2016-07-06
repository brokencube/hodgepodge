<?php
namespace HodgePodge\Core;

use HodgePodge\Session\Database as SessionHandler;
use Automatorm\Database\Query;
use HodgePodge\Exception;

class Session
{
    public static $singleton = null;
    public static function create($connectionname)
    {
        static $singleton = null;
        return static::$singleton = new static($connectionname);
    }

    public static function session()
    {
        return static::$singleton;
    }
    
    protected $timeout;
    protected $connection;
    protected $handler;
    protected $ready = false;
    protected function __construct($connectionname)
    {
        $this->timeout = $sessiontimeout;
        $this->connection = $connectionname;
    }
    
    protected function init()
    {
        if ($this->ready) return;
        $this->ready = true;
        
        $params = session_get_cookie_params();
        ini_set('session.save_path', $this->connection);
        ini_set('session.gc_maxlifetime', $params['lifetime']);
        $this->handler = new SessionHandler($params['lifetime']);
        session_set_save_handler($this->handler, true);
        session_start();
        setcookie(session_name(), session_id(), time() + $params['lifetime'], $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    
    public function lock()
    {
        $this->init();
        $this->handler->writeLock(session_id());    
    }
    
    public function &__get($var)
    {
        $this->init();
        return $_SESSION[$var];
    }

    protected function __isset($var)
    {
        $this->init();
        return array_key_exists($var, $_SESSION);
    }
    
    protected function &__set($var, $value)
    {
        $this->lock();
        return $_SESSION[$var] = $value;
    }   
}
