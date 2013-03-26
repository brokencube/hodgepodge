<?php
namespace LibNik\Exception\HTTP;

class Forbidden extends LibNik\Exception\HTTP
{
	public function __construct($message = null, $data = null, \Exception $previous_exception = null)
	{
		parent::__construct(403, $message, $data, $previous_exception);
	}
}