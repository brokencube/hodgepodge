<?php
namespace HodgePodge\Core;

class Log implements \Psr\Log\LoggerInterface
{
    /* Quick shortcut functions for quick and easy logging. */
    public function emergency($message, array $context = [])
    {
        return $this->log(\Psr\Log\LogLevel::EMERGENCY, $message, $context);
    }

    public function alert($message, array $context = [])
    {
        return $this->log(\Psr\Log\LogLevel::ALERT, $message, $context);
    }

    public function critical($message, array $context = [])
    {
        return $this->log(\Psr\Log\LogLevel::CRITICAL, $message, $context);
    }

    public function error($message, array $context = [])
    {
        return $this->log(\Psr\Log\LogLevel::ERROR, $message, $context);
    }
    
    public function warning($message, array $context = [])
    {
        return $this->log(\Psr\Log\LogLevel::WARNING, $message, $context);
    }
    
    public function notice($message, array $context = [])
    {
        return $this->log(\Psr\Log\LogLevel::NOTICE, $message, $context);
    }
    
    public function info($message, array $context = [])
    {
        return $this->log(\Psr\Log\LogLevel::INFO, $message, $context);
    }
    
    public function debug($message, array $context = [])
    {
        return $this->log(\Psr\Log\LogLevel::DEBUG, $message, $context);
    }

    protected $log = [];
    public function log($level, $message, array $context = [])
    {
        if ($this->disabled) return;
        
        switch($level)
        {
            case \Psr\Log\LogLevel::EMERGENCY:
            case \Psr\Log\LogLevel::ALERT:
            case \Psr\Log\LogLevel::CRITICAL:
            case \Psr\Log\LogLevel::ERROR:
            case \Psr\Log\LogLevel::WARNING:
            case \Psr\Log\LogLevel::NOTICE:
            case \Psr\Log\LogLevel::INFO:
            case \Psr\Log\LogLevel::DEBUG:
                $this->log[] = [
                    'level' => $level,
                    'message' => $message,
                    'context' => $context
                ];
                break;
                
            default:
                throw new \Psr\Log\InvalidArgumentException();
        }
    }
    
    /* Options */

    /*****************************************************/
    public static $singleton;

    public static function get()
    {
        // Return Log::$log - if it doesn't exists, stick a new Log object in it.
        return static::$singleton ?: (static::$singleton = new static());
    }
    
    public static function off($off = true)
    {
        static::get()->disable($off);
    }
    
    public static function isOff()
    {
        return static::get()->disabled;
    }
    
    /*****************************************************/
    
    public $disabled = false;

    // Output on script end.
    public function __destruct()
    {
        if ($this->disabled) return;
        $this->output();
    }
    
    protected function disable($disable = true)
    {
        $this->disabled = $disable;
    }

    // $log('message');
    public function __invoke()
    {
        switch(func_num_args())
        {
            case 0:
                return $this->log(\Psr\Log\LogLevel::DEBUG, 'blank message logged');
            
            case 1:
                if (func_get_arg(0) instanceof Query) {
                    return $this->logQuery(func_get_arg(0));
                }
                return $this->log(\Psr\Log\LogLevel::NOTICE, func_get_arg(0));
            
            case 2:
                return $this->log(func_get_arg(0), func_get_arg(1));
            
            case 3:
            default:
                return $this->log(func_get_arg(0), func_get_arg(1), func_get_arg(2));
        }
    }

    public function output()
    {
        if ($this->disabled) return;
        
        $log = $this->processLogIntoConsoleCommands();
        
        if ($log) {
            echo "<script type='text/javascript'>\n";
            echo "try {\n";
            foreach($log['messages'] as $line) echo $line;
            echo "} catch(e) {}\n";
            echo "</script>\n";
        }
    }

    public function getJsonOutput()
    {
        if ($this->disabled) return;
        
        return $this->processLogIntoConsoleCommands();
    }
    
    protected function processLogIntoConsoleCommands()
    {
        if ($this->log) {
            foreach($this->log as $log) {
                if(!$log['context']['query']) {
                    $array[] = "console.".$this->jsLevel($log['level'])."('" . static::format($log['message']) . "');";
                } else {
                    // Collect stats about queries, but don't log to screen yet
                    $query_count++;
                    foreach ($log['context'] as $debug) {
                        if (array_key_exists('time', $debug)) $query_time += $debug['time'];
                        $query_errors += isset($debug['error']) ? 1 : 0;
                    }
                }
            }
            $query_time = number_format($query_time, 4);
            
            // We've outputted all of the normal messages, now output query stuff
            if ($query_count) {
                $array[] = "console.groupCollapsed('QUERIES: {$query_count} queries in {$query_time}s with {$query_errors} errors');";
                
                foreach($this->log as $log) {
                    if($log['context']['query']) {
                        if ($log['context']['error']) $log['message'] = '[ERROR] ' . Log::format($log['message']);
                        $message = "console.groupCollapsed('".Log::format($log['message'])."');\n";
                        if ($log['context']['error']) $message .=  "console.error('".Log::format($log['context']['error'])."');\n";
                        
                        $message .= "console.log('".Log::format($log['context']['query']);
                        if (!empty($log['context']['data'])) $message .= "\\n".Log::format(print_r($log['context']['data'],1));
                        $message .= "');\n";
                        $message .=  "console.groupEnd();\n";
                        
                        $array[] = $message;
                    }
                }
                $array[] = "console.groupEnd();\n";
            }
            
            $output = [
                'script' => $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'],
                'messages' => $array    
            ];
        }
        
        $this->log = [];
        
        return $output;
    }

    public static function format($data, $strip_whitespace = false)
    {
        if ($strip_whitespace) {
            return preg_replace('/\s+/m', ' ', addslashes($data));
        }
        return str_replace(array("\n", "\r"), array('\n', '\r'), addslashes($data));
    }

    protected function jsLevel($level)
    {
        switch ($level) {
            case \Psr\Log\LogLevel::EMERGENCY:
            case \Psr\Log\LogLevel::ALERT:
            case \Psr\Log\LogLevel::CRITICAL:
            case \Psr\Log\LogLevel::ERROR:
                return 'error';
            case \Psr\Log\LogLevel::WARNING:
                return 'warn';
            case \Psr\Log\LogLevel::NOTICE:
                return 'log';
            case \Psr\Log\LogLevel::INFO:
            case \Psr\Log\LogLevel::DEBUG:
                return 'info';            
        }
    }
}
