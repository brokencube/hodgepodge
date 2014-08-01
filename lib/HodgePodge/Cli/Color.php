<?php

namespace HodgePodge\Cli;

class Color {
    protected static $ANSI_CODES = array(
        "off"        => 0,
        "bold"       => 1,
        "italic"     => 3,
        "underline"  => 4,
        "blink"      => 5,
        "inverse"    => 7,
        "hidden"     => 8,
        "black"      => 30,
        "red"        => 31,
        "green"      => 32,
        "yellow"     => 33,
        "blue"       => 34,
        "magenta"    => 35,
        "cyan"       => 36,
        "white"      => 37,
        "bg-black"   => 40,
        "bg-red"     => 41,
        "bg-green"   => 42,
        "bg-yellow"  => 43,
        "bg-blue"    => 44,
        "bg-magenta" => 45,
        "bg-cyan"    => 46,
        "bg-white"   => 47
    );
    
    public function colorise($colorstring, $string)
    {
        $colors = explode('+', $colorstring);
        $code = '';
        foreach ($colors as $color)
        {
            $code .= "\033[" . self::$ANSI_CODES[$color] . "m";    
        }
        $reset = "\033[" . self::$ANSI_CODES['off'] . "m";
        
        // For any set of ansicodes already in the string that don't start with a reset, prepend a reset 
        $string = preg_replace('/((?:\033\[[1-9]\d*?m)(?:\033\[\d+?m)*)/', $reset . '$1', $string);
        
        // For any reset that is not followed by other codes, append the current code to the reset
        $string = preg_replace('/(?:\033\[0m(?!\033\[))/', $reset . $code, $string);
        
        // If there are any codes purely sandwiched between two reset, crush the entire set into one reset
        $string = preg_replace('/(?:\033\[0m)(?:\033\[\d+?m)*(?:\033\[0m)/', $reset, $string);
        
        // Return colored string
        return $code . $string . $reset;
    }
    
    public function red($string)         { return static::colorise('red+bold', $string);     }
    public function green($string)       { return static::colorise('green+bold', $string);   }
    public function yellow($string)      { return static::colorise('yellow+bold', $string);  }
    public function blue($string)        { return static::colorise('blue+bold', $string);    }
    public function magenta($string)     { return static::colorise('magenta+bold', $string); }
    public function cyan($string)        { return static::colorise('cyan+bold', $string);    }
    public function white($string)       { return static::colorise('white+bold', $string);   }
    public function darkred($string)     { return static::colorise('red', $string);          }
    public function darkgreen($string)   { return static::colorise('green', $string);        }
    public function darkyellow($string)  { return static::colorise('yellow', $string);       }
    public function darkblue($string)    { return static::colorise('blue', $string);         }
    public function darkmagenta($string) { return static::colorise('magenta', $string);      }
    public function darkcyan($string)    { return static::colorise('cyan', $string);         }
    public function darkwhite($string)   { return static::colorise('white', $string);        }
}
