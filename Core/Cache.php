<?php
namespace LibNik\Core;

class Cache extends Cache_Lite
{
    static public $lifetime = array('default' => 3600);

    private $cache_group = 'default';
    private $cache_id = null;
    
    static function lifetime($lifetime, $group = 'default')
    {
        self::$lifetime[$group] = $lifetime;
    }

    public function __construct($id, $group = null)
    {
        global $config;
        $this->cache_id = $id;
        if($group) $this->cache_group = $group;
        
        $options = array(
            'lifeTime' => self::$lifetime[$group ?: 'default'] ?: 3600,
            'pearErrorMode' => CACHE_LITE_ERROR_DIE,
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

    // Alternate syntax: get: $data = $cache()  save: $cache($data);
    public function __invoke()
    {
        if(func_num_args()) {
            return $this->save(func_get_arg(0));
        } else {
            return $this->get();
        }
    }

    public function save($contents)
    {
        global $config;
        if ($config['cache']['disable']) return false;
        
        return parent::save($contents, $this->cache_id, $this->cache_group);
    }
}
