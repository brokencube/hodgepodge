<?php
namespace HodgePodge\Core;

class Log
{
    /* Quick shortcut functions for quick and easy logging. */
    public static function debug($message, $level = 1)
    {
        self::get()->log('d', $message);
    }
    
    public static function notice($message, $level = 1)
    {
        self::get()->log('n', $message);
    }
    
    public static function warning($message, $level = 1)
    {
        self::get()->log('w', $message);
    }
    
    public static function error($message, $level = 1)
    {
        self::get()->log('e', $message);
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
    
    public function isDisabled()
    {
        return static::get()->disabled;
    }
    
    /*****************************************************/
    
    protected $disabled = false;
    protected $log = [];
    
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
                return $this->log('log', 'blank message logged');
            
            case 1:
                if (func_get_arg(0) instanceof Query) {
                    return $this->logQuery(func_get_arg(0));
                }
                return $this->log('log', func_get_arg(0));
            
            case 2:
            default:
                return $this->log(func_get_arg(0), func_get_arg(1));
        }
    }

    public function log($level, $message)
    {
        if ($this->disabled) return;
        
        $log = array(
            'level' => $this->determineLevel($level),
            'message' => $message,
            'debug' => $debug
        );
        
        $this->log[] = $log;
    }

    public function logQuery(Query $query)
    {
        if ($this->disabled) return;
        
        $time = number_format($query->debug['total_time'] * 1000, 2);
        $queries = $query->debug['count'] == 1 ? '1 QUERY' : $query->debug['count'] . " QUERIES";
        $con = "Con:".$query->name;
        $preview = static::format(substr(implode(' ', $query->sql),0,100),true);
        
        $log['header'] = "{$time}ms {$con} | {$queries}: $preview";
        $log['error'] = $query->error;
        $log['debug'] = $query->debug;        
        $log['query'] = $query->sql;
        
        $this->log[] = $log;        
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
                if(!$log['query']) {
                    $array[] = "console.".$log['level']."('" . static::format($log['message']) . "');";
                } else {
                    // Collect stats about queries, but don't log to screen yet
                    $query_count++;
                    $query_time += $log['debug']['total_time'];
                    $query_errors += $log['error'] ? 1 : 0;
                }
            }
            $query_time = number_format($query_time, 4);
            
            // We've outputted all of the normal messages, now output query stuff
            if ($query_count) {
                $array[] = "console.groupCollapsed('QUERIES: {$query_count} queries in {$query_time}s with {$query_errors} errors');";
                
                foreach($this->log as $log) {
                    if($log['query']) {
                        if ($log['error']) $log['header'] = '[ERROR] ' . $log['header'];
                        $message = "console.groupCollapsed('{$log['header']}');\n";
                        if ($log['error']) $message .=  "console.error('".Log::format($log['error'])."');\n";
                        
                        foreach($log['query'] as $q) {
                            $message .= "console.log('".Log::format($q)."');\n";
                        }
                        $message .=  "console.groupEnd();\n";
                        
                        $array[] = $message;
                    }
                }
                $array[] = "console.groupEnd();\n";
            }
            
            $output = array(
                'script' => $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'],
                'messages' => $array    
            );            
        }
        
        $this->log = array();
        
        return $output;
    }

    public static function format($data, $strip_whitespace = false)
    {
        if ($strip_whitespace) {
            return preg_replace('/\s+/m', ' ', addslashes($data));
        }
        return str_replace(array("\n", "\r"), array('\n', '\r'), addslashes($data));
    }

    protected function determineLevel($string)
    {
        switch (strtolower(substr($string,0,1))) {
            case 'e': // error
            case 'f': // fatal error
                return 'error';
            
            case 'w': // warning
                return 'warn';
            
            case 'd': // debug
            case 'l': // log
            default:
                return 'log';
            
            case 'n': // notice
            case 'i': // info
                return 'info';            
        }
    }
}
