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
                    $list = array_merge($list, $value->toArray());
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
                    $list = array_merge($list, $value->toArray());
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
        if ($array instanceof Collection) $array = $array->toArray();
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
        $copy = $this->container;
        uasort($copy, $function);
        return new static($copy);
    }
    
    public function natSort($key = null)
    {
        if (!$key)
        {
            return $this->sort(function ($a, $b) {
                return strnatcmp((string) $a, (string) $b);
            });
        }
        else
        {
            return $this->sort(function ($a, $b) {
                return strnatcmp($a->{$key}, $b->{$key});
            });            
        }
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
        $copy = $this->container;
        
        if ($array instanceof Collection) $array = $array->toArray();
        if (!is_array($array)) throw new InvalidArgumentException('Orm\Collection->add() expects an array');
        
        $copy = array_values(array_merge($copy, $array));
        
        return new static($copy);
    }
    
    // Remove any items in this collection that match the where clause
    public function remove($where_array)
    {
        return $this->not($where_array);
    }
    
    public function not($where_array)
    {
        $copy = $this->container;
        
        // Loop over items
        foreach ($copy as $item_key => $item) {
            // Loop over filters
            foreach ($where_array as $property => $value_list) {
                // Each filter can have several acceptable values -- force single item to array
                if (!is_array($value_list)) $value_list = array($value_list);
                // Check each value - if we find a matching value than remove this item
                foreach ($value_list as $value) {
                    if ($item->$property == $value) {
                        unset($copy[$item_key]);
                        break 2;
                    }
               }    
            }
        }
        
        $copy = array_values($copy);
        
        return new static($copy);
    }

    public function filter($where_array)
    {
        $copy = $this->container;

        // Loop over items
        foreach ($copy as $item_key => $item) {
            // Loop over filters
            foreach ($where_array as $property => $value_list) {
                // Each filter can have several acceptable values -- force single item to array
                if (!is_array($value_list)) $value_list = array($value_list);
                // Check each value - if we find a matching value than skip to the next filter.
                foreach ($value_list as $value) {
                    if ($item->$property == $value) {
                        continue 2;
                    }
                }
                // Failed to break of loop, so the current value matches none of the
                // values for the current filter, therefore remove the item
                unset($copy[$item_key]);
            }
        }
        
        $copy = array_values($copy);
        
        return new static($copy);
    }
}
