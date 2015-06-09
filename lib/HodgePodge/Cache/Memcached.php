<?php
namespace HodgePodge\Cache;

use HodgePodge\Interfaces;
use HodgePodge\Core;

class Memcached implements Interfaces\Cache
{
    protected $group;
    protected $id;
    protected $key;
    protected $memcached;
    
    public function __construct($id, $group = null)
    {
        global $config;
        
        $this->id = $id;
        $this->group = is_null($group) ? 'default' : $group;
        $this->key = 'HodgePodge:Cache:' . $this->group . ':' . $this->id;
        
        list($server, $port) = explode(':', $config['memcache']['url']);
        $this->memcached = new \Memcached();
        $this->memcached->addServer($server, $port);
    }

    public function get()
    {
        global $config;
        if ($config['cache']['disable']) {
            return false;
        }
        
        return $this->memcached->get($this->key);
    }

    public function save($contents)
    {
        global $config;
        if ($config['cache']['disable']) {
            return false;
        }
        
        $lifetime = Core\Cache::$lifetime[$this->group ?: 'default'] ?: 3600;
        
        return $this->memcached->set($this->key, $contents, $lifetime);
    }
    
    public function delete()
    {
        global $config;
        if ($config['cache']['disable']) {
            return false;
        }
        
        return $this->memcached->delete($this->key);
    }
}
