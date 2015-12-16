<?php
namespace HodgePodge\Template;

use HodgePodge\Interfaces\Templater;

class Smarty extends \Smarty implements Templater
{
    /* INTERFACE METHODS */
    public static function page($template, $data = array())
    {
        $tpl = new static();
        $tpl->assign('data', $data);
        echo $tpl->render($template);
    }

    // Assign function dictated by Templater interface provided by underlying \Smarty class
    #public function assign($name, $value)
    
    public function render($template)
    {
        return $this->fetch($template . '.tpl');
    }
    
    //////////
    
    public function __construct()
    {
        global $config;
        // Class Constructor.
        
        parent::__construct();
        
        $this->setTemplateDir(ROOT.'/templates');
        $this->setCompileDir(ROOT.'/templates_c');
        $this->setConfigDir(ROOT.'/config');
        $this->setCacheDir(ROOT.'/cache');
        
        $this->registerPlugin('modifier', 'currency', array('\\HodgePodge\\Template\\Smarty', 'smartyModifierCurrency'));
        $this->registerPlugin('modifier', 'safe', array('\\HodgePodge\\Template\\Smarty', 'smartyModifierSafe'));
        $this->registerPlugin('modifier', 'datetime', array('\\HodgePodge\\Template\\Smarty', 'smartyModifierReformatDate'));
        
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
