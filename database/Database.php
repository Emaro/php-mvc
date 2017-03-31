<?php

/**
 * User: Joel Häberli
 * Date: 03.03.2017
 * Time: 11:31
 */
class Database {
    
    private $host;
    private $username;
    private $password;
    private $database;
    
    private $connection;
    
    public function __construct(String $host, String $username, String $password, String $database) {
        
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->database = $database;
    }
    
    public function getDatabaseConnection() {
        
        $conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->database, $this->username, $this->password);
        if ($conn->connect_error == "") {
            $this->connection = $conn;
            return $conn;
        } else {
            die("Error while loading database connection. Please check the params");
        }
    }
    
    public function performQuery(Queryable $model, String $queryPattern) {
        
        $stmt = $this->connection->prepare($queryPattern);
        
        return $stmt->execute($model->getQueryParameter());
    }
}