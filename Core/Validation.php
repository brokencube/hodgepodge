<?php
namespace LibNik\Core;

use LibNik\Exception;

class Validation
{
    protected $variables = array();
    protected $name = '';
    protected $errors = array();
    protected $source = array();
    
    public function __sleep()
    {
        return ['source'];
    }
    
    public function __wakeup() {}
    
    public function __construct(array $source = null)
    {
        if (is_null($source)) {
            $this->source = $_POST;
        } else {
            $this->source = $source;
        }
    }
    
    public function __get($var)
    {
        return $this->variables[$var];
    }
    
    public function __invoke($name)
    {
        $this->variables[$name] = $this->source[$name];
        $this->current = $name;
        return $this;
    }
    
    public function required($message = "This field is required")
    {
        if (!$this->variables[$this->current]) {
            $this->errors[$this->current] = $message;
        }
        return $this;
    }
    
    public function min($min = 3, $message = "This field must be at least 3 characters long")
    {
        if (strlen($this->variables[$this->current]) < $min) {
            $this->errors[$this->current] = $message;
        }        
        return $this;
    }
    
    public function max($max = 3, $message = "This field must not be longer than 3 characters")
    {
        if (strlen($this->variables[$this->current]) > $max) {
            $this->errors[$this->current] = $message;
        }        
        return $this;
    }
    
    public function email($message = "This is not a valid email address")
    {
        if (filter_var($this->variables[$this->current], FILTER_VALIDATE_EMAIL) === FALSE) {
            $this->errors[$this->current] = $message;
        }        
        return $this;
    }
    
    public function matches($var = 'password', $message = "Passwords don't match")
    {
        if ($this->variables[$this->current] != $this->variables[$var]) {
            $this->errors[$this->current] = $message;
        }                
        return $this;
    }
    
    public function error()
    {    
        return current(array_slice($this->errors,0,1));
    }
    
    public function errors()
    {
        return $this->errors;
    }
}
