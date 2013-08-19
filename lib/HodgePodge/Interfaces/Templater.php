<?php

namespace HodgePodge\Interfaces;

interface Templater
{
    public function assign($name, $value);      // Assign {$name} with $value
    public function render($template);          // Return a $string containing rendered template
    public static function page($template, $data);  // Display a rendered template, assigning $data to {$data}
}