<?php
namespace HodgePodge\Core;

use HodgePodge\Exception;

class SQL {
    public function __construct($sql, $data = [])
    {
        $this->sql = $sql;
        $this->data = $data;
    }
    
    public function execute(\PDO $pdo)
    {
        $pdostatement = $pdo->prepare($sql);
        $pdostatement->execute($data);
        return $pdostatement;
    }
}
