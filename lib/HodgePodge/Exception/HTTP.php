<?php
namespace HodgePodge\Exception;

class HTTP extends Generic
{
    protected $header = 'HTTP/1.1 200 OK';
	public function __construct($code = 200, $message = null, $data = null, \Exception $previous_exception = null)
	{
		parent::__construct('HTTPCODE', $message, $data, $previous_exception);
        
        $this->code = $code;
	}
    
    public function getHeader()
    {
        return $this->header;
    }
}