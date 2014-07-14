<?php
namespace HodgePodge\Exception\HTTP;

class Gone extends \HodgePodge\Exception\HTTP
{
    protected $header = 'HTTP/1.1 410 Gone';
    public function __construct($message = null, $data = null, \Exception $previous_exception = null)
    {
        parent::__construct(410, $message, $data, $previous_exception);
    }
}

