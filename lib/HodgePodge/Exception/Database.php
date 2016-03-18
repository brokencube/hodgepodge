<?php
namespace HodgePodge\Exception;

use HodgePodge\Core;

class Database extends Generic
{
	public function __construct($code, $message, Core\Query $query = null, \Exception $previous_exception = null)
	{
		parent::__construct($code, $message, $query, $previous_exception);
	}
}