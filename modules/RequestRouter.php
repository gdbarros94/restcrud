<?php
// RequestRouter.php

require_once 'DatabaseHandler.php';

class RequestRouter {
    private $pdo;
    private $dbname;

    public function __construct($pdo, $dbname) {
        $this->pdo = $pdo;
        $this->dbname = $dbname;
    }

    public function route() {
        $requestUri = $_SERVER['REQUEST_URI'];
        $uriParts = explode('?', trim($requestUri, '/'));
        $path = $uriParts[0];
        $method = $_SERVER['REQUEST_METHOD'];

        $dbHandler = new DatabaseHandler($this->pdo, $this->dbname);

        if ($path === 'database' && $method === 'GET') {
            echo json_encode($dbHandler->getTablesAndRelations());
            return;
        }

        if (!empty($path)) {
            $tableName = explode('/', $path)[0];

            try {
                $result = $this->handleRequest($method, $dbHandler, $tableName);
                echo json_encode($result);
            } catch (Exception $e) {
                echo json_encode(['error' => $e->getMessage()]);
            }
        }
    }

    private function handleRequest($method, $dbHandler, $tableName) {
        switch ($method) {
            case 'GET':
                return $dbHandler->executeGetQuery($tableName, $_GET);
            case 'POST':
                $data = json_decode(file_get_contents('php://input'), true);
                return $dbHandler->insertData($data);
            case 'PUT':
                $data = json_decode(file_get_contents('php://input'), true);
                return $dbHandler->updateData($data);
            case 'DELETE':
                $data = json_decode(file_get_contents('php://input'), true);
                return $dbHandler->deleteData($data);
            default:
                throw new Exception('Método não permitido');
        }
    }
}
