<?php
namespace HodgePodge\Common;

abstract class Collection implements \ArrayAccess, \Iterator, \Countable, \JsonSerializable
{
    protected $container = [];

    //////// Interface Methods ///////
    public function jsonSerialize()
    {
        return $this->container;
    }
    
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }

    public function offsetExists($offset)
    {
        return isset($this->container[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->container[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->container[$offset]) ? $this->container[$offset] : null;
    }
    
    public function rewind()
    {
        reset($this->container);
    }
    
    public function current()
    {
        return current($this->container);
    }

    public function key()
    {
        return key($this->container);
    }

    public function next()
    {
        return next($this->container);
    }
    
    public function valid()
    {
        return current($this->container) !== false;
    }
    
    public function count()
    {
        return count($this->container);
    }
    
    public function first()
    {
        return array_slice($this->container, 0, 1)[0];
    }

    public function last()
    {
        return array_slice($this->container, count($this->container) - 1, 1)[0];
    }
}
