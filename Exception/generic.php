<?php
namespace LibNik\Exception;

class Generic extends \RuntimeException
{
	protected $errorcode;
	protected $errorcodes = array();
	protected $data;

	public function __construct($code = 0, $message = null, $data = null, \Exception $previous_exception = null)
	{
		$this->errorcode = $code;
		
		if (!is_numeric($code))
		{
			$code = array_search($code, $this->errorcodes);
		}
		
		parent::__construct($message, (int) $code, $previous_exception);
		$this->data = $data;
	}

	public function get_data()
	{
		return $this->data;
	}
	
	public function get_message()
	{
		return $this->getMessage();
	}

	public function get_code()
	{
		return $this->getCode();
	}

	public function is($code)
	{
		return ($code === $this->errorcode);
	}	
}