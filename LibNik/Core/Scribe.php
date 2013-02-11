<?php
namespace LibNik\Core;

class Scribe
{
    protected static $display = array(
        'css' => array(),     // Array of css files to include
        'lesscss' => array(), // Array of lesscss files to include
        'js' => array(),      // Array of js files to include
        'meta' => '',         // <meta keywords> tag
        'title' => '',        // <title> tag
    );
    
    const ENGINE_SMARTY = '\\LibNik\\Template\\Smarty';
    const ENGINE_RAIN = '\\LibNik\\Template\\Rain';
    
    protected static $engine = Scribe::ENGINE_SMARTY;
    
    public static function page($template, $data = array())
    {
        if (!$template) throw new Exception\Generic(0, 'No template given');
        
        $tpl = new static::$engine;
        $env = Env::get();
        
        /* Mobile templates */
        if ($env->mobileBrowser())
        {
            // Check if mobile version of template exists
            if (file_exists(ROOT . '/templates/mobile/' . $template))
            {
                $template = 'mobile/' . $template; // Exists - use mobile content
            }
        }
        
        $tpl->assign('path', Router::path());
        $tpl->assign('env',	$env);
        $tpl->assign('display',	self::$display);
        $tpl->assign('data', $data);
        
        $tpl->render($template);
    }

    public static function HTTP_404()
    {
        header("HTTP/1.0 404 Not Found");
        self::page($data, 'standard/404.tpl');
        exit;
    }

    public static function maintenence()
    {
        header("HTTP/1.1 503 Service Unavailable");
        self::page(array(), 'standard/maintenence.tpl');
        exit;
    }

    ///////////////////////

    // A whole load of 'set' functions to add (deduplicated) data to the display instance
    static function add_js(array $array)
    {
        self::$display['js'] = array_unique(array_merge(self::$display['js'], $array));
    }

    static function add_lesscss(array $array)
    {
        self::$display['lesscss'] = array_unique(array_merge(self::$display['lesscss'], $array));
    }

    static function add_css(array $array)
    {
        self::$display['css'] = array_unique(array_merge(self::$display['css'], $array));
    }

    static function add_meta(array $array)
    {
        self::$display['meta'] = array_unique(array_merge(self::$display['meta'], $array));
    }

    static function title($title)
    {
        self::$display['title'] = $title;
    }
}
