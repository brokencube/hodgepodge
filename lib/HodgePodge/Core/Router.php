<?php
namespace HodgePodge\Core;

use HodgePodge\Exception\HTTP;

class Router
{
    protected static $root;                         // Base_dir for this application
    protected static $path;                         // URL path as passed to router
                                                    // - e.g. https://www.example.com/[example/url]?query_string -> 'example/url'
    protected static $filename;                     // Local path to php file as calculated from URL
    protected static $parts = array();              // Array of secondary arguments passed as directories within the url
    protected static $unrouted_parts = array();       // Parts of the path not used to calculate local path
    public static $default = 'home';                // Path to default to if no path detected
    public static $extension = '.php';                // Path to default to if no path detected
    public static $route_prefix = ['controllers'];  // List of controller locations (from /$root)
    
    // Based on the manipulation of the URL by mod_rewrite, decide what php file in to load.
    public static function route($routepath = null, $root = null)
    {
        // Calculate root (parameter -> global const -> guessed)
        static::$root = $root ?: ROOT ?: dirname($_SERVER['DOCUMENT_ROOT']);
        if (substr(static::$root, -1) != DIRECTORY_SEPARATOR) static::$root .= DIRECTORY_SEPARATOR;
        
        // Get path to route
        static::$path = $routepath ?: $_REQUEST['routepath'];
        static::$path = str_replace(chr(0), '', static::$path); // Protect against poison null byte attacks
        
        // Split the url on '/'
        static::$parts = array_filter(explode('/', static::$path));
        if (!static::$parts[0]) static::$parts[0] = static::$default; // If first part is empty, replace with default
        
        // Keep popping file path tokens from static::$parts until we find a routable target, or run out of parts
        while (count(static::$parts)) {
            
            // Build filename
            foreach(static::$route_prefix as $pre) {
                $path = static::$root
                    . $pre . DIRECTORY_SEPARATOR
                    . implode(DIRECTORY_SEPARATOR, static::$parts);
                    
                // If file exists, then include controller and return.
                if(file_exists($path . static::$extension)) {
                    return static::$filename = $path . static::$extension;
                }
            }
            
            // Pop the next token off the path, and prepend it to the list of unrouteable parts
            array_unshift(static::$unrouted_parts, array_pop(static::$parts));
        }
        
        // Could not find a page to route to - throw exception
        throw new HTTP\NotFound();
    }
    
    public static function args()
    {
        return static::$unrouted_parts;
    }

    public static function path()
    {
        return static::$path;
    }
    
    /* UTILITY FUNCTIONS */
    // Utility function to replace bad characters for urls
    public static function clean($url)
    {
        $string = str_replace(
            array('&', '%', '£', '/', '\\', "'", '"', ':', '#', '@', '?', ',', '>', '<'),
            array('and', '%25', '%C2%A3', ' '),
            $url
        );
        
        $string = preg_replace('/\s/', '-', $string);
        $string = preg_replace('/-+/', '-', $string);
        
        return $string;
    }

    // Redirect to $url if current url is not $url
    public static function autofix($url)
    {
        // Strip the hash + query string
        if(strpos($url, '#') !== false) $url = substr($url, 0, strpos($url, '#'));
        if(strpos($url, '?') !== false) $url = substr($url, 0, strpos($url, '?'));
        
        if ($url && $url != $_SERVER['SCRIPT_URL']) {
            static::redirect($url);
        }
    }
    
    public static function secure($be_secure = true)
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
        $url = str_replace(chr(0), '', $url); // Protect against poison null byte attacks
        header("Location: $url");
        exit;
    }
}