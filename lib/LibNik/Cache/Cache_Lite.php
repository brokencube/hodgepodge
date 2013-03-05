<?php
namespace LibNik\Cache;

use LibNik\Interfaces;

class Cache_Lite extends \Cache_Lite implements Interfaces\Cache
{
    private $cache_group = 'default';
    private $cache_id = null;
    
    public function __construct($id, $group = null)
    {
        global $config;
        $this->cache_id = $id;
        if($group) $this->cache_group = $group;
        
        $options = array(
            'lifeTime' => LibNik\Core\Cache::$lifetime[$group ?: 'default'] ?: 3600,
            'pearErrorMode' => CACHE_LITE_ERROR_DIE, // Removes reliance on PEAR_Error class (Though obviously not as good!)
            'cacheDir' => ($config['cache_dir']?:$config['cache']['dir']),
            'automaticSerialization' => true,
        );
        parent::__construct($options);
    }

    public function get()
    {
        global $config;
        if ($config['cache']['disable']) return false;
        
        return parent::get($this->cache_id, $this->cache_group);
    }

    public function save($contents)
    {
        global $config;
        if ($config['cache']['disable']) return false;
        
        return parent::save($contents, $this->cache_id, $this->cache_group);
    }
}
