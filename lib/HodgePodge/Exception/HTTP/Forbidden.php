<?php
namespace HodgePodge\Exception\HTTP;

class Forbidden extends \HodgePodge\Exception\HTTP
{
    protected $header = 'HTTP/1.1 403 Forbidden';
	public function __construct($message = null, $data = null, \Exception $previous_exception = null)
	{
		parent::__construct(403, $message, $data, $previous_exception);
	}
}
