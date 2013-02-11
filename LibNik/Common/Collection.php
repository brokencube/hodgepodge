<?php
namespace LibNik\Common;

abstract class Collection implements \ArrayAccess, \Iterator, \Countable, \JsonSerializable
{
    protected $container;

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
    
    function rewind()
    {
        reset($this->container);
    }
    
    function current()
    {
        return current($this->container);
    }

    function key()
    {
        return key($this->container);
    }

    function next()
    {
        return next($this->container);
    }
    
    function valid()
    {
        return current($this->container) !== false;
    }
    
    function count()
    {
        return count($this->container);
    }    
}
