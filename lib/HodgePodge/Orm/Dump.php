<?php

namespace HodgePodge\Orm;

use HodgePodge\Common;
use HodgePodge\Core;
use HodgePodge\Exception;

class Dump
{   
    public static function dump(Model $model)
    {
        $dump = new static();
        return $dump->_dump($model);
    }
    
    public function _dump(Model $model)
    {
        $data_access = function ($var) {
            return $this->$var;
        };
        
        $closure = function () use ($data_access){
            $data = $data_access->bindTo($this->_data, $this->_data);
            $schema = $data('model');
            $seen = [];
            
            $output = "<pre>";
            $output .= "<span><strong>".get_class()."</strong></span>\n";
            $output .= "  <span><strong>id</strong></span> => ".$this->id."\n";
            $output .= "  <span><strong>connection</strong></span> => ".$this->database."\n";
            $output .= "  <span><strong>table</strong></span> => ".$this->table."\n";
                        
            $output .= "  <span><strong>object_properties</strong></span> =>\n";
            foreach (get_object_vars($this) as $key => $value)
            {
                $output .= "    " . \HodgePodge\Orm\Dump::format($key, $value, $seen);
                $seen[$key] = true;
            }
            
            $output .= "  <span><strong>dynamic_properties</strong></span> =>\n";
            foreach (get_class_methods($this) as $method)
            {
                if (substr($method, 0, 10) == '_property_')
                {
                    $key = substr($method, 10);
                    $value = $this->$key;
                    $output .= "    " . \HodgePodge\Orm\Dump::format($key, $value, $seen);
                    $seen[$key] = true;
                }
            }

            $output .= "  <span><strong>data_properties</strong></span> =>\n";
            foreach ($data('data') as $key => $value)
            {
                $output .= "    " . \HodgePodge\Orm\Dump::format($key, $value, $seen);
                $seen[$key] = true;
            }
            
            $output .= "  <span><strong>external_tables</strong></span> =>\n";
            $output .= "    <span><strong>1-1</strong></span> =>\n";
            if ($schema['one-to-one']) foreach ($schema['one-to-one'] as $key => $contents)
            {
                $value = $this->$key;
                
                $output .= "      " . \HodgePodge\Orm\Dump::format($key, $value, $seen);
                $seen[$key] = true;
            }

            $output .= "    <span><strong>*-1</strong></span> =>\n";
            if ($schema['many-to-one']) foreach ($schema['many-to-one'] as $key => $contents)
            {
                $value = $this->$key;
                
                $output .= "      " . \HodgePodge\Orm\Dump::format($key, $value, $seen);
                $seen[$key] = true;
            }

            $output .= "    <span><strong>1-*</strong></span> =>\n";
            if ($schema['one-to-many']) foreach ($schema['one-to-many'] as $key => $contents)
            {
                $value = $this->$key;
                
                $output .= "      " . \HodgePodge\Orm\Dump::format($key, $value, $seen);
                $seen[$key] = true;
            }

            $output .= "    <span><strong>*-*</strong></span> =>\n";
            if ($schema['many-to-many']) foreach ($schema['many-to-many'] as $key => $contents)
            {
                $value = $this->$key;
                
                $output .= "      " . \HodgePodge\Orm\Dump::format($key, $value, $seen);
                $seen[$key] = true;
            }
            
            $output .= "</pre>\n";
            
            return $output;
        };
        
        $c = $closure->bindTo($model, $model);
        return $c();    
    }
    
    public static function format($key, $value, $seen)
    {
        switch (true)
        {                
            case $value instanceof Model:
                $namespace = explode('\\', get_class($value));
                $class = array_pop($namespace);

                $type = 'Model';
                $display1 = implode('\\', $namespace) . '\\';
                $display2 = $class;                
                $display3 = " ".$value->id;
            break;

            case $value instanceof Collection:
                $ids = [];
                foreach ($value as $obj)
                {
                    $ids[] = $obj->id;
                }
                
                if ($ids)
                {
                    $namespace = explode('\\', get_class($obj));
                    $class = array_pop($namespace);                    
                    $display1 = implode('\\', $namespace) . '\\';
                    $display2 = $class;                
                }
                else
                {
                    $display1 = 'empty';                    
                }
                
                $type = 'Collection';
                $display3 = " [".implode(',', $ids)."]";
            break;
            
            case $value instanceof Time:
                $type = 'DateTime';
                $display3 = $value->format('Y-m-d H:i:s');
            break;
            
            case is_object($value):
                $namespace = explode('\\', get_class($value));
                $class = array_pop($namespace);
                
                $type = 'object';
                $display1 = implode('\\', $namespace) . '\\';
                $display2 = $class;
            break;
            
            case is_bool($value):
                $type = 'boolean';
                $display3 = $value?'true':'false';
            break;
            
            case is_null($value):
                $type = 'null';
                $display3 = "NULL";
            break;

            case is_string($value):
                $type = 'string';
                $display3 = '"' . $value .'"';
            break;
            
            default:
                $type = gettype($value);
                $display3 = $value;
            break;
        }
        
        $string = "";
        
        if (array_key_exists($key, $seen))
        {
            return
                "<del style='color: #999999;'><strong>$key</strong> => <small>$type</small> ".
                $display1.
                $display2.
                $display3.
                "</del>\n";   
        }
        else
        {
            return "<strong>$key</strong> => <small>$type</small> ".
            "<span style='color: #999999;'>$display1</span>".
            "<span style='color: #000077;'>$display2</span>".
            "<span style='color: #cc0000;'>$display3</span>".
            "\n";
        }
    }
}