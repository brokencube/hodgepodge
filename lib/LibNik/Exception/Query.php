<?php
namespace LibNik\Exception;

use LibNik\Core;

class Query extends Generic
{
	public function __construct(Core\Query $query)
	{
		parent::__construct('QUERY_EXCEPTION', 'Query error: '.$query->error, $query);
	}
}