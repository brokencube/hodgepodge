<?php
namespace LibNik\Core;

class Autoload
{
    private $namespace;
    private $dir;
        
    public function __construct($namespace, $dir)
    {
        $this->namespace = $namespace;
        $this->dir = $dir . (substr($dir, -1) != '/' ? '/' : '');
    }
    
    public static function register($namespace, $dir)
    {
        $autoloader = new static($namespace, $dir);
        spl_autoload_register(array($autoloader, 'load'));
    }
    
    public function load($classname)
    {
        if (strpos($classname, $this->namespace) !== 0) {
            return false;
        }
        
        if ($lastNsPos = strrpos($classname, '\\')) {
            $namespace = substr($classname, 0, $lastNsPos);
            $classname = substr($classname, $lastNsPos + 1);
            $filename  = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
        }
        
        $filename .= str_replace('_', DIRECTORY_SEPARATOR, $classname) . '.php';
        
        if (file_exists($this->dir . $filename))
        {
            require_once $this->dir . $filename;            
        }
    }
}
