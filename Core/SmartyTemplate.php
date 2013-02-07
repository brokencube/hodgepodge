<?php
namespace LibNik\Core;

class SmartyTemplate extends Smarty
{    
    static function render($template, $data = array())
    {
        $tpl = new static();
        $tpl->assign('data', $data);
        return $tpl->fetch($template);        
    }

    function __construct()
    {
        global $config;
        // Class Constructor.
        
        parent::__construct();
        
        $this->template_dir = $config['smarty']['dir'].'/templates';
        $this->compile_dir  = $config['smarty']['dir'].'/templates_c';
        $this->config_dir   = $config['smarty']['dir'].'/config';
        $this->cache_dir    = $config['smarty']['dir'].'/cache';
        
        $this->registerPlugin('modifier', 'currency', array('\\LibNik\\Core\\SmartyTemplate', 'smartyModifierCurrency'));
        $this->registerPlugin('modifier', 'safe', array('\\LibNik\\Core\\SmartyTemplate', 'smartyModifierSafe'));
        $this->registerPlugin('modifier', 'reformat_date', array('\\LibNik\\Core\\SmartyTemplate', 'smartyModifierReformatDate'));
        
        $this->assign('config', $config);
    }
    
    // Format money.  e.g  {$amount|currency} will print out £12.30   if $amount = 12.3
    public function smartyModifierCurrency($string, $currency = 'GBP')
    {
        // If this is not a number then don't modify the input
        if (!is_numeric($string)) return $string;
        
        switch($currency) {
            case 'GBP':
                $currency_symbol = '&pound;';
                break;
            case 'USD':
                $currency_symbol = '$';
                break;
            case 'EUR':
                $currency_symbol = '&euro;';
                break;
            default:
                $currency_symbol = '?';
                break;
        }
        
        // Return formatted number preceded by currency symbol
        return $currency_symbol . sprintf('%01.2f', $string);
    }
    
    //shorthand function for gez's utf-8 safe htmlentities function
    public function smartyModifierSafe($string)
    {
        return htmlentities((string) $string, ENT_COMPAT, 'UTF-8');
    }
    
    public function smartyModifierReformatDate($date, $format)
    {
        return date($format, strtotime($date));
    }    
}
