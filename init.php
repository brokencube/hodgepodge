<?php
/* Dependancies: PHP 5.3+ (5.4+ recommended)
 * 
 * Core\Cache >>> Cache_Lite (https://pear.php.net/package/Cache_Lite/)
 * Core\SmartyTemplate >>> Smarty3 (http://www.smarty.net/)
 * Core\Database, Core\Query, Orm\* >>> Mysql 5.0+
 * Core\Image >>> IMagick PHP extension
 * Core\Socket >>> Linux OS 
 */

// Constants
define('MYSQL_DATE', 'Y-m-d H:i:s');

// PHP <5.4 Interface compatibility
if (!interface_exists('JsonSerializable', false))
{
    interface JsonSerializable {
        public function jsonSerialize();
    }
}

// Start up the autoloader
require_once __DIR__ . '/lib/LibNik/Core/Autoload.php'; // Load autoloader
\LibNik\Core\Autoload::register('LibNik\\', __DIR__ . '/lib');
