<?php
namespace LibNik\Exception;
use LibNik\Core;

class Database extends Generic
{
	protected $errorcodes = array(
		'NO_DETAILS',
		'CONNECTION_NOT_DEFINED',
		'CONNECTION_FAILED',
		'QUERY_LOCKED',
		'QUERY_ERROR'
	);

	public function __construct($code, $message, Core\Query $query = null)
	{
		parent::__construct($code, $message, $query);
	}
}