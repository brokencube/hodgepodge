<?php
namespace LibNik\Cache;

use LibNik\Interfaces;

class Memcached implements Interfaces\Cache
{
    protected $group = 'default';
    protected $id = null;
    protected $key = null;
    protected $memcached = null;
    
    public function __construct($id, $group = null)
    {
        global $config;
        
        $this->id = $id;
        if($group) $this->group = $group;
        $this->key = 'LibNik:Cache:' . $this->group . ':' . $this->id;
        
        list($server, $port) = explode(':', $config['memcache']['url']);
        $this->memcached = new \Memcached();
        $this->memcached->addServer($server, $port);
    }

    public function get()
    {
        global $config;
        if ($config['cache']['disable']) return false;
        
        return $this->memcached->get($this->key);
    }

    public function save($contents)
    {
        global $config;
        if ($config['cache']['disable']) return false;
        
        $lifetime = \LibNik\Core\Cache::$lifetime[$this->group ?: 'default'] ?: 3600;
        
        return $this->memcached->set($this->key, $contents, $lifetime);
    }
}
