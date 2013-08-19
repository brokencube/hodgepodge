<?php
namespace LibNik\Core;

class Router
{
    protected static $path;                     // Full URL path as passed to router
    protected static $filename;                 // Local path to php file as calculated from URL
    protected static $parts = array();          // Array of secondary arguments passed as directories within the url
    protected static $routed_parts = array();   // Parts of the path used to calculate local path
    public static $default = 'home';            // Path to default to if no path detected
    public static $route_prefix = 'webroot';    // List of locations of files for particular file extensions (.html, .ajax etc)
    
    // Based on the manipulation of the URL by mod_rewrite, decide what php file in to load.
    public static function route($routepath = null)
    {
        static::$path = $routepath ?: $_REQUEST['routepath'];
        static::$path = str_replace(chr(0), '', static::$path); // Protect against poison null byte attacks
        
        // Split the url on '/'
        static::$parts = array_filter(explode('/', static::$path));
        
        // Generate the first possible filename for this URL
        return static::generateRoutedFilename();
    }

    protected static function generateRoutedFilename()
    {
        // Grab the next part of the URL and merge into the routed path
        static::$routed_parts[] = array_shift(static::$parts) ?: static::$default;

        static::$filename =
            (static::$route_prefix ? static::$route_prefix . DIRECTORY_SEPARATOR : '')
            . implode(DIRECTORY_SEPARATOR, static::$routed_parts)
            . '.php';
        
        return static::$filename;
    }

    public static function reroute()
    {
        // Generate the next possible filename for this URL
        return static::generateRoutedFilename();
    }
    
    public static function args()
    {
        if (!isset(static::$path)) static::route();
        return static::$parts;
    }

    public static function path()
    {
        if (!isset(static::$path)) static::route();
        return static::$path;
    }
    
    // Utility function to replace bad characters for urls
    public static function clean($url)
    {
        $string = str_replace(
            array('&', '/', '\\', "'", '"', ':', '#', '@', '?', ','),
            array('and', ' ', ' '),
            $url
        );
        
        $string = preg_replace('/\s/', '-', $string);
        $string = preg_replace('/-+/', '-', $string);
        
        return $string;
    }

    // Auto-repairs urls - if the current url doesn't match the url for the object in question, then redirect to the objects URL.
    public static function autofix($url)
    {
        // Strip the hash from the end of the url
        if(strpos($url, '#') !== false) $url = substr($url, 0, strpos($url, '#'));
        if(strpos($url, '?') !== false) $url = substr($url, 0, strpos($url, '?'));
        
        if ($url && $url != $_SERVER['SCRIPT_URL']) {
            static::redirect($url);
        }
    }
    
    public function secure($be_secure = true)
    {
        $env = Env::get();
        // Force https
        if ($be_secure and !$env->secure()) {
            static::redirect('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        }
        
        // Force http
        if (!$be_secure and $env->secure()) {
            static::redirect('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        }        
    }
        
    public static function redirect($url)
    {
        header("Location: $url");
        exit;
    }
}