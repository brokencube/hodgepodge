<?php
namespace LibNik\Exception\HTTP;

class Error extends \LibNik\Exception\HTTP
{
    protected $header = 'HTTP/1.1 500 Internal Server Error';
	public function __construct($message = null, $data = null, \Exception $previous_exception = null)
	{
		parent::__construct(500, $message, $data, $previous_exception);
	}
}
