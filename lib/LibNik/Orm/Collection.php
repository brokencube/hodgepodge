<?php
namespace LibNik\Orm;

use LibNik\Common;

class Collection extends Common\Collection
{
    public function __get($parameter)
    {
        $list = array();
        foreach($this->container as $item) {
            $value = $item->$parameter;
            if ($item instanceof Model) {
                if ($value instanceof Collection) {
                    $list = array_merge($list, $value->to_array());
                } else {
                    $list[] = $value;
                }
            } else {
                $list[] = $value;
            }
        }
        return new static($list);
    }

    public function __call($name, $arguments)
    {
        $list = array();
        foreach($this->container as $item) {
            if (!method_exists($item, $name)) {
                throw new BadMethodCallException();
            }
            
            $value = $item->$name($arguments);
            if ($item instanceof Model) {
                if ($value instanceof Collection) {
                    $list = array_merge($list, $value->to_array());
                } else {
                    $list[] = $value;
                }
            } else {
                $list[] = $value;
            }
        }
        return new static($list);
    }

    public function __construct($array = null)
    {
        if (is_null($array)) $array = array();
        if ($array instanceof Collection) $array = $array->to_array();
        if (!is_array($array)) throw new InvalidArgumentException('Orm\Collection::__construct() expects an array - ' . gettype($array) . ' given');
        
        $this->container = $array;
    }
    
    public function toArray($value = null, $key = 'id')
    {
        $return = array();
        
        if(!$value) {
            foreach($this->container as $item) {
                $return[$item->$key] = $item;
            }
            return $return;            
        }
        
        foreach($this->container as $item) {
            $return[$item->$key] = $item->$value;
        }
        
        return $return;
    }
    
    //////// Collection modifiers ////////
    public function sort($function)
    {
        uasort($this->container, $function);
        return $this;
    }
    
    public function slice($start, $length)
    {
        return new static(array_slice($this->container, $start, $length));
    }
    
    public function reverse()
    {
        return new static(array_reverse($this->container));
    }
    
    // Merge another array into this collection
    public function merge($array)
    {
        return $this->add($array);
    }
    
    public function add($array)
    {
        if ($array instanceof Collection) $array = $array->to_array();
        if (!is_array($array)) throw new InvalidArgumentException('Orm\Collection->add() expects an array');
        
        $this->container = array_values(array_merge($this->container, $array));
        
        return $this;
    }
    
    // Remove any items in this collection that match the where clause
    public function remove($where_array)
    {
        return $this->not($where_array);
    }
    
    public function not($where_array)
    {
        foreach ($this->container as $item_key => $item) {
            foreach ($where_array as $property => $value_list) {
                if (!is_array($value_list)) $value_list = array($value_list);
                foreach ($value_list as $value) {
                    if ($item->$property == $value) {
                        unset($this->container[$item_key]);
                        break 2;
                    }
                }    
            }
        }
        
        $this->container = array_values($this->container);
        
        return $this;
    }
}
