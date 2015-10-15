<?php
namespace HodgePodge\Exception;

class Session extends Generic
{
	public function __construct($code, \Exception $previous_exception = null)
	{
		parent::__construct($code, $code, null, $previous_exception);
	}
}