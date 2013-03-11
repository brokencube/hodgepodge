<?php
namespace LibNik\Core;

class Env
{
    public static function get() 
    {
        static $singleton;
        return $singleton ?: $singleton = new static();
    }
        
    /* DEFINE VARIABLES */
    protected $site_mode = 'development';    // Site mode: development, live
    
    protected $revision = null;              // Revision of the site currently running
    protected $mobile_browser = null;        // Are we on a mobile browser
    protected $mobile_browser_cookie = null; // Are we on a mobile browser
    protected $ip = null;                    // Current user's IP address
    protected $ie6 = false;                  // Is the user's browser IE6?
    protected $secure = null;                // HTTPS?
    
    /* PUBLIC ACCESS FUNCTIONS */

    public function siteMode() 
    {
        return $this->site_mode;
    }

    public function developmentMode() 
    {
        return $this->site_mode != 'live';
    }

    public function liveMode() 
    {
        return $this->site_mode == 'live';
    }
    
    public function secure() 
    {
        return $this->secure;
    }
    
    public function isIE6() 
    {
        return $this->ie6;
    }

    public function getSiteRevision() 
    {
        return $this->revision;
    }

    public function getIP() 
    {
        return $this->ip;
    }

    public function mobileBrowser($ignore_cookie = false) 
    {
        // Special case - User has opted to view the full non-mobile site, but we still want to know if they are actually on a mobile.
        // For example, to display a "Show mobile site" link.
        if (!$ignore_cookie && isset($this->mobile_browser_cookie)) {
            return ($this->mobile_browser_cookie == 'true');
        }
        return $this->mobile_browser;
    }
        
    /* PUBLIC CONTROL FUNCTIONS */
    protected function __construct() 
    {
        $this->site_mode = $_SERVER['PARAM1'] == 'live' ? 'live' : 'development';
        $this->secure = ($_SERVER['HTTPS'] == 'on' or $_SERVER['HTTP_X_FORWARDED_PORT'] == '443');
        
        preg_match('#[-_/]([^-_/]+?)(/webroot)?/?$#', $_SERVER['DOCUMENT_ROOT'], $matches);
        $this->revision = $matches[1];
        
        /* Mobile Sitemode Cookies and Detection */
        if (isset($_GET['set_mobile_browser'])) {
            setcookie('set_mobile_browser', $_GET['set_mobile_browser'], strtotime('+1 day'));
            $this->mobile_browser_cookie = $_GET['set_mobile_browser'];
        } elseif (isset($_COOKIE['set_mobile_browser'])) {
            $this->mobile_browser_cookie = $_COOKIE['set_mobile_browser'];
        }
        
        // From http://detectmobilebrowser.com/
        $this->mobile_browser = preg_match(
            '/android|avantgo|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i',
            $_SERVER['HTTP_USER_AGENT']
		) || preg_match(
            '/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|e\-|e\/|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(di|rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|xda(\-|2|g)|yas\-|your|zeto|zte\-/i',
            substr($_SERVER['HTTP_USER_AGENT'],0,4)
        );
        
        if (substr($_SERVER['HTTP_USER_AGENT'], 0, 34) == 'Mozilla/4.0 (compatible; MSIE 6.0;') {
            $this->ie6 = true;
        }
                
        $this->ip = $this->findIPAddress();
    }
    
    private function findIPAddress() 
    {
        if (getenv("HTTP_CLIENT_IP") && strcasecmp(getenv("HTTP_CLIENT_IP"), "unknown")) {
            $ip = getenv("HTTP_CLIENT_IP");
        } elseif (getenv("HTTP_X_FORWARDED_FOR") && strcasecmp(getenv("HTTP_X_FORWARDED_FOR"), "unknown")) {
            $ip = getenv("HTTP_X_FORWARDED_FOR");
        } elseif (getenv("REMOTE_ADDR") && strcasecmp(getenv("REMOTE_ADDR"), "unknown")) {
            $ip = getenv("REMOTE_ADDR");
        } elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], "unknown")) {
            $ip = $_SERVER['REMOTE_ADDR'];
        } else {
            $ip = "unknown";
        }	
        return($ip);        
    }
}
