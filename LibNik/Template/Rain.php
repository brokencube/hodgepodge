<?php
namespace LibNik\Template;

use LibNik\Interfaces\Templater;

class Rain extends \Rain\Tpl implements Templater
{
    /* INTERFACE METHODS */
    public static function page($template, $data = array())
    {
        $tpl = new static();
        $tpl->assign('data', $data);
        $tpl->draw($template);
    }

    // Assign function dictated by Templater interface provided by underlying \Rain\TPL class
    #public function assign($name, $value)
    
    public function render($template)
    {
        return $this->draw($template, true);
    }
    
    //////////
    
    public function __construct()
    {
        global $config;
        // Class Constructor.
        
        parent::__construct();
        
        $this->objectConfigure(array(
            'base_url' => null,
            'tpl_dir' => WEBROOT.'/templates',
            'cache_dir' => WEBROOT.'/cache',
        ));
        
        $this->assign('config', $config);
    }
}
