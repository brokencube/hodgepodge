<?php
namespace HodgePodge\Exception\HTTP;

class Error extends \HodgePodge\Exception\HTTP
{
    protected $header = 'HTTP/1.1 500 Internal Server Error';
	public function __construct($message = null, $data = null, \Exception $previous_exception = null)
	{
		parent::__construct(500, $message, $data, $previous_exception);
	}
}
