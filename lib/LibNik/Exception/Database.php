<?php
namespace LibNik\Exception;

use LibNik\Core;

class Database extends Generic
{
	public function __construct($code, $message, Core\Query $query = null)
	{
		parent::__construct($code, $message, $query);
	}
}