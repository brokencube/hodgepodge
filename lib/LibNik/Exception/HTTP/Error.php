<?php
namespace LibNik\Exception\HTTP;

class Error extends LibNik\Exception\HTTP
{
	public function __construct($message = null, $data = null, \Exception $previous_exception = null)
	{
		parent::__construct(500, $message, $data, $previous_exception);
	}
}