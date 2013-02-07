<?php
namespace LibNik\Core;

class QueryOptions
{
    protected $sort;
    protected $limit;
    protected $offset;
    
    public function __get($var)
    {
        return $this->{$var};
    }
    
    public function sort($sortby)
    {
        $this->sort = $sortby;
        return $this;
    }
    
    public function limit($limit, $offset = 0)
    {
        $this->limit = intval($limit);
        $this->offset = intval($offset);
        return $this;
    }
}
