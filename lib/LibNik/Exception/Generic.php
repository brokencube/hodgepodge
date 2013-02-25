<?php
namespace LibNik\Exception;

class Generic extends \RuntimeException
{
	protected static $label_messages = array();

	protected $label;   // A codeword for easy exception recognition
	protected $data;    // Any data associated with the exception (e.g parameters that caused the exception)

	public function __construct($label = 'UNKNOWN', $message = null, $data = null, \Exception $previous_exception = null)
	{
		$this->label = $label;
		
		parent::__construct($message, 0, $previous_exception);
		$this->data = $data;
	}

	public function getData()
	{
		return $this->data;
	}

	public function getLabel()
	{
		return $this->label;
	}
}