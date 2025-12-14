<?php
require_once 'core/DBConnection.php';
class DBManager{
    private $connection;
    public function __construct(){
        $this->connection = new DBConnection();
        $this->connection->connect('mysql', 'localhost', '3306', 'fitacate', 'root', 'root');
    }
    public function getPDO(): PDO{
        return $this->connection->getPDO();
    }
    public function closeConnection(){
        $this->connection->close();
    }
}