<?php
class DBConnection{
    protected ?PDO $pdo;
    public function connect($protocol, $host, $port, $database, $username, $password){
        $dsn = "$protocol:host=$host;port=$port;dbname=$database;charset=utf8";
        $this->pdo = new PDO($dsn, $username, $password);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function getPDO(): PDO{
        return $this->pdo;
    }

    public function close(){
        $this->pdo = null;
    }


}