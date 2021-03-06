<?php

namespace HodgePodge\Interfaces;

interface Cache
{
    public function __construct($id, $group = null);
    public function get();
    public function save($data);          // Return a $string containing rendered template
    public function delete();
}