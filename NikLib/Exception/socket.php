<?php
namespace LibNik\Exception;
	
class Socket extends Generic
{
	const NO_SOCKET_FILE = 2;
	const IO_ERROR = 4;
	const BROKEN_PIPE = 32;
	
	public function __construct($socket = null)
	{
		if ($socket) {
			parent::__construct(socket_last_error($socket), 'Socket error');
			socket_clear_error($socket);
		} else {
			parent::__construct(socket_last_error(), 'Socket error');
		}
	}
	
	public function get_error()
    {
        return $this->errorcode;
    }
    
	public function get_error_string()
    {
        return socket_strerror($this->errorcode);
    }
}