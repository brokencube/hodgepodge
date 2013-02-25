<?php
namespace LibNik\Core;

use LibNik\Exception;

/**
 * Class to easily build Unix Sockets for IPC
 *
 * Base usage:
 * $socket = Socket::connect('filename.sock');
 * while (!$open_socket = $socket->accept());
 * 
 */
class Socket
{
    private $socket;
    private $socket_location;
    private $unlink_on_destruct;
    public $data;
    
    // Get a new Socket client which is connected to the supplied socket file.
    public static function connect($socket_location)
    {
        // Create the socket
        if(!$socket = @socket_create(AF_UNIX, SOCK_STREAM, 0)) {
            throw new Exception\Socket();
        }
        
        // Connect the socket to the socket file
        if (! @socket_connect($socket, $socket_location)) {
            throw new Exception\Socket($socket);
        }
        
        // Construct the Socket object using the socket made above
        return new static($socket, $socket_location);
    }
    
    // Get a new Socket server which listens for messages on the supplied socket file
    public static function open($socket_location)
    {
        // Create the socket
        if(!$socket = @socket_create(AF_UNIX, SOCK_STREAM, 0)) {
            throw new Exception\Socket();
        }
        
        // Set up options on the socket. On failure, delete the socket file, so it can be created clean next time.
        if (
            !@socket_bind($socket, $socket_location) or
            !@socket_listen($socket) or
            !@socket_set_nonblock($socket)
        ) {
            unlink($socket_location);
            throw new Exception\Socket($socket);
        }
        
        // Construct the Socket object using the socket made above        
        return new static($socket, $socket_location, true);    
    }
    
    // Socket object (thin wrapper over the socket)
    private function __construct($socket, $socket_location, $unlink_on_destruct = false)
    {
        $this->socket = $socket;
        $this->socket_location = $socket_location;
        $this->unlink_on_destruct = (bool) $unlink_on_destruct;
    }
    
    // Close an open socket server
    public function close()
    {
        if ($this->socket) {
            @socket_shutdown($this->socket, 2);
            
            /* UNIX Sockets don't always close immediately. This makes the system block until
             * the socket can be fully released.
             *
             * See: http://uk.php.net/manual/en/function.socket-close.php#66810
             */
            $arrOpt = array('l_onoff' => 1, 'l_linger' => 1);
            @socket_set_block($this->socket);
            @socket_set_option($this->socket, SOL_SOCKET, SO_LINGER, $arrOpt);
            @socket_close($this->socket);
            
            if ($this->unlink_on_destruct) {
                @unlink($this->socket_location);
            }
        }
    }
    
    // Close an open socket server if the object is destroyed
    function __destruct()
    {
        $this->close();
    }
    
    // Accept an incoming connection from another socket. This is the other end of the socket originating from the client.
    function accept()
    {
        $socket = @socket_accept($this->socket);
        
        return $socket ? new static($socket, $this->socket_location) : false;
    }
    
    // Accept an incoming connection from another socket. Block until we have a connection.
    function acceptBlock()
    {
        @socket_set_block($this->socket);    
        $socket = @socket_accept($this->socket);
        @socket_set_nonblock($this->socket);    
        
        return $socket ? new static($socket, $this->socket_location) : false;
    }
    
    // Read data from a socket connection.
    function read()
    {
        $null = null; // Required workaround for references - see Warning on http://php.net/manual/en/function.socket-select.php
        $socket = array($this->socket);
        $changed = @socket_select($socket, $null, $null, 0, 25);
        // Somethings changed? We have data!
        if ( $changed > 0 ) {
            // Read from the socket
            $string = @socket_read($this->socket, 1024 * 1024);
            
            // If the string is explicitly false or empty string then the socket has been closed by the client. Close our end of the connection too.
            if ($string === false or $string === '') {
                $this->close();
                return false;
            } else {
                return $string;
            }
        }
        
        return '';
    }
    
    // Write data to the socket.
    function write($string)
    {
        $bool = socket_write($this->socket, $string, strlen($string));        
        if ($bool === false) throw new Exception\Socket($this->socket);
    }
}
