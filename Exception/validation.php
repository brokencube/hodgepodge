<?php
namespace LibNik\Exception;
	
class Validation extends Generic
{
    public function __construct($message = null, $data = null, \Exception $previous_exception = null)
    {
        parent::__construct(0, $message, $data, $previous_exception);
    }
}

?>
