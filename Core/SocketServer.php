<?php
namespace LibNik\Core;

use LibNik\Exception;

/* Simple CLI Daemon using Unix Sockets
 *
 * Basic implementation:
 *
 * class MyServer extends SocketServer
 * {
 *    function process_step() {
 *        echo "... Do whatever it is this server does ...";
 *    }
 *    
 *    function process_connection($socket, $message) {
 *        $socket->write('Response to connection to outside world');
 *        $socket->write($this->capture_string); // Send any captured output over the socket connection
 *    }
 * }
 *
 * $server = new MyServer('/path/to/socket.file', true);
 * $server->start();
 * echo "Server closed cleanly";
 */

abstract class SocketServer
{
    private $open_connections = array();    // List of sockets objects connected to this server
    private $server_socket;            // The Socket this server is using
    protected $IPC;                // Messages aimed at the server (usually from clients)
    protected $capture_output = false;    // Do we want to capture output from the server
    protected $capture_string = '';        // All stdout output from the last server 'tick'
    
    abstract public function processStep();
    abstract public function processConnection($socket, $message);
    
    public function __construct($socket_location, $capture_output = false)
    {
        $this->socket_location = $socket_location;
        $this->capture_output = (bool) $capture_output;
    }
    
    public function start()
    {
        // Turn off logging and start handlers for SIGINT (^C) and SIGTERM (service stop)
        Log::off();
        pcntl_signal(SIGINT, array($this, 'shutdown'));
        pcntl_signal(SIGTERM, array($this, 'shutdown'));
        
        // Open a new socket and make it globally r/w 
        $this->server_socket = Socket::open($this->socket_location);
        chmod($this->socket_location, 0777);
        
        // Starting capturing output if desired
        if ($this->capture_output) ob_start();
        
        // Main server loop
        while(pcntl_signal_dispatch()) { // Each loop, check for interrupt signals.
            // Run the process function - capture the output if option set.
            $this->processStep();
            
            // Check for new connections and output to any existing connections
            $this->processAllConnections();
            
            // Someone, somewhere has requested the server close down. Close all connections.
            if ($this->IPC == 'shutdown') {
                foreach ($this->open_connections as $socket) {
                    $socket->close();
                }
                break; // Exit the while loop
            }
        }
        
        // Throw away anything we have captured but haven't already output
        if ($this->capture_output) ob_end_clean();
        
        // All connections have been closed, and the 'shutdown' message passed - shut the socket down.
        $this->server_socket->close();
        
        // Reset the signal handlers before we return to non-server land.
        pcntl_signal(SIGINT, SIG_DFL);
        pcntl_signal(SIGTERM, SIG_DFL);
    }
    
    public function processAllConnections()
    {
        if ($this->capture_output) {
            // Store the captured output for this round of processing and reset the buffer
            $this->capture_string = ob_get_contents();
            ob_end_clean();
            ob_start();            
        }
        
        // We have new connections waiting - accept them and add them to the list.
        while($new = $this->server_socket->accept()) {
            $this->open_connections[] = $new;
        }
        
        // Foreach open connection, check whether it has been closed by the client, else run the per_connection function
        foreach($this->open_connections as $key => $sock) {
            if (($received = $sock->read()) === false) {
                // Connection closed by client
                unset($this->open_connections[$key]);
            } else {
                try {
                    // Process this connection
                    $this->processConnection($sock, $received);                    
                } catch (Exception\Socket $e) {
                    echo "Socket Error - closing external socket\n";
                    $sock->close();
                    unset($this->open_connections[$key]);
                }
            }
        }        
    }
    
    // Calling this function will cause the server to stop and close all connections once the current process_step() has finished processing.
    public function shutdown()
    {
        echo "Shutdown method called. Waiting for current process tick to finish before stopping server.\n";
        $this->IPC = 'shutdown';
    }
}
