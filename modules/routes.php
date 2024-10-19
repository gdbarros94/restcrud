<?php
// routes.php

function routeRequest($pdo, $dbname) {
    $requestUri = $_SERVER['REQUEST_URI'];
    $uriParts = explode('?', trim($requestUri, '/'));
    $path = $uriParts[0];
    $method = $_SERVER['REQUEST_METHOD'];

    if (!empty($path)) {
        $tableName = explode('/', $path)[0];

        try {
            switch ($method) {
                case 'GET':
                    $result = executeGetQuery($pdo, $tableName, $_GET);
                    break;

                case 'POST':
                    $data = json_decode(file_get_contents('php://input'), true);
                    $result = insertData($pdo, $data);
                    break;

                case 'PUT':
                    $data = json_decode(file_get_contents('php://input'), true);
                    $result = updateData($pdo, $data);
                    break;

                case 'DELETE':
                    $data = json_decode(file_get_contents('php://input'), true);
                    $result = deleteData($pdo, $data);
                    break;

                default:
                    throw new Exception('Método não permitido');
            }
            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
