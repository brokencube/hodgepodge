<?php
namespace HodgePodge\Core;

use HodgePodge\Exception;

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
    
    const ENGINE_SMARTY = '\\HodgePodge\\Template\\Smarty';
    const ENGINE_RAIN = '\\HodgePodge\\Template\\Rain';
    
    public static $engine = Scribe::ENGINE_SMARTY;
    
    public static function render($template, $data = array())
    {
        if (!$template) throw new Exception\Generic('NO_TEMPLATE', 'No template given');
        
        $tpl = new static::$engine;
                
        $tpl->assign(static::$extras);
        $tpl->assign('path', Router::path());
        $tpl->assign('env', Env::get());
        $tpl->assign('display',	static::$display);
        $tpl->assign('data', $data);
        
        return $tpl->render($template);
    }

    public static function page($template, $data = array())
    {
        echo static::render($template, $data);
    }
    ///////////////////////

    // Add (deduplicated) data to the Scribe::$display variable
    public static function add($var, array $array)
    {
        self::$display[$var] = array_merge((array) self::$display[$var], $array);
    }
    
    // Couple of common helper functions
    public static function description($description)
    {
        self::add('meta', array('description' => $description));
    }

    public static function title($title)
    {
        self::$display['title'] = $title;
    }
}
