<?php
namespace LibNik\Core;

class Router
{
    protected static $path;                // Full URL path as passed to router
    protected static $filename;            // Local path to php file as calculated from URL
    protected static $args;                // Array of secondary arguments passed as directories within the url
    protected static $default = 'home';    // Path to default to if no path detected
    protected static $lookups = array(     // List of locations of files for particular file extensions (.html, .ajax etc)
        'html' => 'webroot'
    );
    
    // Based on the manipulation of the URL by mod_rewrite, decide what php file in to load.
    public static function route($routepath = null)
    {
        static::$path = $routepath ?: $_REQUEST['routepath'];
        static::$path = str_replace(chr(0), '', static::$path); // Protect against poison null byte attacks
        
        // Regex the path to extract information
        /* Examples:
         * example.com/home                           -> (home) () ()
         * example.com/home/search/everything.ajax    -> (home) (search/everything) (ajax)
         * example.com/home/search                    -> (home) (search) ()
         * example.com/search.ajax                    -> (search) () (ajax)
         * example.com                                -> (home) () ()        // 'home' taken from static::$default!
         */
        preg_match(
            '/
                ^\/?               # Anchor to start of path
                ([^.\/]+)          # Grab the first part of the path (to be the filename), until either a dot or forwardslash is found
                \/?(.*?)           # Grab the middle of the path (directory structure after first level)
                (?:\.([a-zA-Z]+))? # Optionally, grab an "extension" e.g.   ".ajax" at the end of the path
                $                  # Anchor to end of path
            /x',
            static::$path,
            $matches
        );
        
        // Split up matches from above into sensibly named varibles;
        if ($matches) list($full, $file, $args, $extension) = $matches;
        if (!$file) $file = static::$default;
        
        // Lookup specific extensions for alternate directories
        if (!$extension) $extension = 'html';
        $dir = static::$lookups[$extension];
        
        // Assemble the desired filename
        static::$filename = $dir . '/' . $file . '.php';
        static::$args = array_filter(explode('/', $args));
        
        return static::$filename;
    }
    
    // [FIXME] Is this really the best way to do this... feels slightly dirty?
    public static function reroute()
    {
        $old_filename = substr(static::$filename, 0, -4); // Remove .php from end;
        $next_part = array_shift(static::$args) ?: static::$default;
        return static::$filename = $old_filename . '/' . $next_part . '.php';
    }
    
    public static function args()
    {
        if (!isset(static::$path)) static::route();
        return static::$args;
    }

    public static function path()
    {
        if (!isset(static::$path)) static::route();
        return static::$path;
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
    
    public static function redirect($url)
    {
        header("Location: $url");
        exit;
    }

    public static function addLookup(array $array)
    {
        static::$lookups = array_merge(static::$lookups, $array);
    }
}
