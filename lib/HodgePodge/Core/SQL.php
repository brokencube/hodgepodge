<?php
namespace HodgePodge\Core;

use HodgePodge\Exception;

class SQL {
    public $sql;
    public $data;
    
    public function __construct($sql, $data = [])
    {
        $this->sql = $sql;
        if (!is_array($data)) $data = [$data];
        $this->data = $data;
    }
    
    public function execute(\PDO $pdo)
    {
        $pdostatement = $pdo->prepare($this->sql);
        $pdostatement->execute($this->data);
        return $pdostatement;
    }
    
    public function __toString()
    {
        return $this->sql;
    }
}
