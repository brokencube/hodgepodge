<?php
namespace HodgePodge\Exception;

use HodgePodge\Core;

class Query extends Generic
{
	public function __construct(Core\Query $query, \Exception $previous_exception = null)
	{
		parent::__construct('QUERY_EXCEPTION', 'Query error: '.$query->error, $query, $previous_exception);
	}
}