<?php
namespace HodgePodge\Core;

class Cache
{
    const MEMCACHED = 'HodgePodge\\Cache\\Memcached';
    const CACHE_LITE = 'HodgePodge\\Cache\\Cache_Lite';
    
    static public $engine = Cache::MEMCACHED;
    static public $lifetime = array('default' => 3600);

    public static function lifetime($lifetime, $group = 'default')
    {
        static::$lifetime[$group] = $lifetime;
    }

    private $obj;
    
    public function __construct($id, $group = null)
    {
        $this->obj = new static::$engine($id, $group);
    }

    public function get()
    {
        return $this->obj->get();
    }

    // Alternate syntax: get: $data = $cache()  save: $cache($data);
    public function __invoke()
    {
        if (func_num_args()) {
            return $this->obj->save(func_get_arg(0));
        } else {
            return $this->obj->get();
        }
    }

    public function save($contents)
    {
        return $this->obj->save($contents);
    }

    public function delete()
    {
        return $this->obj->delete();
    }
}
