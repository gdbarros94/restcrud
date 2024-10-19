<?php
// config.php

class DatabaseConfig {
    private $host = 'alunostds.dev.br:3308';
    private $dbname = 'app_user';
    private $username = 'app_user';
    private $password = 'QWxVbk9zVERz';

    public function connect() {
        try {
            $pdo = new PDO("mysql:host={$this->host};dbname={$this->dbname}", $this->username, $this->password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $pdo;
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Erro na conexão: ' . $e->getMessage()]));
        }
    }
}

header("Content-Type: application/json");

$databaseConfig = new DatabaseConfig();
$pdo = $databaseConfig->connect();
