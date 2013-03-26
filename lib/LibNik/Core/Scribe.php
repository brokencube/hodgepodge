<?php
namespace LibNik\Core;

use LibNik\Exception;

class Scribe
{
    protected static $display = array(
        'css' => array(),     // Array of css files to include
        'lesscss' => array(), // Array of lesscss files to include
        'js' => array(),      // Array of js files to include
        'meta' => array(),    // <meta> tags
        'title' => '',        // <title> tag
    );
    
    public static $extras = array();
    
    const ENGINE_SMARTY = '\\LibNik\\Template\\Smarty';
    const ENGINE_RAIN = '\\LibNik\\Template\\Rain';
    
    public static $engine = Scribe::ENGINE_SMARTY;
    
    public static function page($template, $data = array())
    {
        if (!$template) throw new Exception\Generic('NO_TEMPLATE', 'No template given');
        
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
        
        $tpl->assign(static::$extras);
        $tpl->assign('path', Router::path());
        $tpl->assign('env', $env);
        $tpl->assign('display',	static::$display);
        $tpl->assign('data', $data);
        
        echo $tpl->render($template);
    }

    ///////////////////////

    // Add (deduplicated) data to the Scribe::$display variable
    static function add($var, array $array)
    {
        self::$display[$var] = array_unique(array_merge(self::$display[$var], $array));
    }
    
    // Couple of common helper functions
    static function description($description)
    {
        self::add('meta', array('description' => $description));
    }

    static function title($title)
    {
        self::$display['title'] = $title;
    }
}
