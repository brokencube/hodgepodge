<?php
namespace LibNik\Core;

class Router
{
    protected static $page;
    protected static $filename;
    protected static $args;
    protected static $path;
    
    // Based on the manipulation of the URL by mod_rewrite, decide what php file in webroot to include.
    // Will look for mobile.<name>.php first if in mobile browser mode.
    public static function route($routepath = null)
    {
        global $config;
        
        if (!$routepath) $routepath = $_REQUEST['routepath'];
        
        // Get an array of path parts
        static::$path = $parts = array_filter(explode('/', $routepath));
        
        // Look for special parts of the url
        while(true) {
            $part = array_shift($parts);
            
            // No part? Home page!
            if (!$part) {
                $part = 'home';
                break;
            }
            
            // If the part is a special string (e.g. /ajax/)
            if (!$special and $config['router']['files'] and array_search( $part, $config['router']['files']() ) !== false) {
                $special = '.' . $part;
                continue;
            }
            
            // If the part is a special string (e.g. /dashboard/)
            if (!$dir and $config['router']['dirs'] and array_search( $part, $config['router']['dirs']() ) !== false) {
                $dir = $part . '/';
                continue;
            }
            
            break;
        }
        
        // Mobile specific file?
        if (Env::get()->mobileBrowser() && file_exists($dir . 'mobile.' . $part . $special . '.php')) {
            $mobile = 'mobile.';
        }
        
        // Assemble the desired filename
        static::$filename = $dir . $mobile . $part . $special;
        static::$page = $dir.$part;
        
        // Left over parts are argument list
        static::$args = $parts;
        
        return static::$filename;
    }
    
    public static function args()
    {
        if (!isset(self::$args)) self::route();
        return self::$args;
    }

    public static function path()
    {
        if (!isset(self::$path)) self::route();
        return self::$path;
    }

    public static function page()
    {
        if (!isset(self::$page)) self::route();
        return self::$page;
    }

    public static function make($obj)
    {
        return '/';
    }
    
    // Auto-repairs urls - if the current url doesn't match the url for the object in question, then redirect to the objects URL.
    public static function autofix($url)
    {
        if (is_object($url)) {
            $url = static::make($url);
        }
        
        // Strip the hash from the end of the url
        if(strpos($url, '#') !== false) $url = substr($url, 0, strpos($url, '#'));
        if(strpos($url, '?') !== false) $url = substr($url, 0, strpos($url, '?'));
        
        if ($url && $url != $_SERVER['SCRIPT_URL']) {
            redirect($url);
        }
    }
    
    public function secure($be_secure = true)
    {
        if (is_null($be_secure) or !Env::live_mode()) return;
        
        // Force https
        if ($be_secure and $_SERVER['HTTPS'] != 'on') {
            redirect('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        }
        
        // Force http
        if (!$be_secure and $_SERVER['HTTPS'] == 'on') {
            redirect('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        }        
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
}
