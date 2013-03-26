<?php
namespace LibNik\Exception\HTTP;

class NotFound extends LibNik\Exception\HTTP
{
    protected $header = 'HTTP/1.0 404 Not Found';
	public function __construct($message = null, $data = null, \Exception $previous_exception = null)
	{
		parent::__construct(404, $message, $data, $previous_exception);
	}
}