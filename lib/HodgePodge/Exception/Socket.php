<?php
namespace HodgePodge\Exception;
	
class Socket extends Generic
{
	const NO_SOCKET_FILE = 2;
	const IO_ERROR = 4;
	const BROKEN_PIPE = 32;
	
	public function __construct($socket = null)
	{
		if ($socket) {
			parent::__construct('SOCKET_ERROR', socket_strerror(socket_last_error($socket)), $socket);
			socket_clear_error($socket);
		} else {
			parent::__construct('SOCKET_ERROR', socket_strerror(socket_last_error()));
		}
	}
}