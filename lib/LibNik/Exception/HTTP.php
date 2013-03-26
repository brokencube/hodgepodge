<?php
namespace LibNik\Exception;

class HTTP extends Generic
{
	public function __construct($code = 200, $message = null, $data = null, \Exception $previous_exception = null)
	{
		parent::__construct('HTTPCODE', $message, $data, $previous_exception);
        
        $this->code = $code;
		$this->data = $data;
	}
}