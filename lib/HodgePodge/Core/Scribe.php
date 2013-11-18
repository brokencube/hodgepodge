<?php
namespace HodgePodge\Core;

use HodgePodge\Exception;

class Scribe
{
    public $extra = array();
    protected $display = array(
        'css' => array(),     // Array of css files to include
        'lesscss' => array(), // Array of lesscss files to include
        'js' => array(),      // Array of js files to include
        'meta' => array(),    // <meta> tags
        'title' => '',        // <title> tag
    );
        
    public function __construct(\HodgePodge\Interfaces\Templater $engine = null)
    {
        if (!$engine) $engine = new \HodgePodge\Template\Smarty;
        $this->engine = $engine;
    }
        
    public function render($template, $data = array())
    {
        if (!$template) throw new Exception\Generic('NO_TEMPLATE', 'No template given');
        
        $tpl = $this->engine;
        $tpl->assign($this->extra);
        $tpl->assign('display', $this->display);
        $tpl->assign('env', Env::get());
        $tpl->assign('data', $data);
        
        return $tpl->render($template);
    }
    
    public function display($template, $data = array())
    {
        echo $scribe->render($template, $data);
    }
    
    ///////////////////////
    public function extra($var, $data)
    {
        $this->extra[$var] = $data;
    }
    
    public function set($var, $data)
    {
        $this->display[$var] = $data;
    }

    // Add (deduplicated) data to the Scribe::$display variable
    public function add($var, array $array)
    {
        $this->display[$var] = array_merge((array) $this->display[$var], $array);
    }
    
    // Some common helper functions
    public function js(array $array)
    {
        $this->add('js', $array);
    }

    // Some common helper functions
    public function css(array $array)
    {
        $this->add('css', $array);
    }

    public function description($description)
    {
        $this->add('meta', array('description' => $description));
    }

    public function title($title)
    {
        $this->set('title', $title);
    }
}
