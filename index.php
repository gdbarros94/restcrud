<?php
// index.php

// Incluir o módulo de autenticação
require_once 'auth_module.php';

// Configuração da autenticação
$authType = 'simple'; // Pode ser 'none', 'simple', 'multiple' ou 'ldap'
$simpleKey = 'sua_chave_secreta_aqui';
$multipleKeysFile = 'keys.json';
$ldapConfig = [
    'host' => 'elowen.gdbarros.com.br',
    'port' => 389,
    'base_dn' => 'dc=gdbarros,dc=com,dc=br',
    'username_attribute' => 'uid',
    // Adicione outras configurações LDAP conforme necessário
];

// Inicializar o módulo de autenticação
$auth = new AuthModule($authType, $simpleKey, $multipleKeysFile, $ldapConfig);

// Verificar a autenticação
if ($authType === 'ldap') {
    $username = $_SERVER['PHP_AUTH_USER'] ?? '';
    $password = $_SERVER['PHP_AUTH_PW'] ?? '';
    if (!$auth->authenticate('', $username, $password)) {
        header('WWW-Authenticate: Basic realm="LDAP Authentication"');
        header('HTTP/1.0 401 Unauthorized');
        die(json_encode(['error' => 'Não autorizado']));
    }
} else {
    $providedKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (!$auth->authenticate($providedKey)) {
        http_response_code(401);
        die(json_encode(['error' => 'Não autorizado']));
    }
}

// 1. Configuração do banco de dados
$host = 'alunostds.dev.br:3308';
$dbname = 'app_user';
$username = 'app_user';
$password = 'QWxVbk9zVERz';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die(json_encode(['error' => 'Erro na conexão: ' . $e->getMessage()]));
}

// Definindo o tipo de resposta
header("Content-Type: application/json");

// Função para obter tabelas e relações
function getTablesAndRelations($pdo, $dbname) {
    static $cache = null;
    if ($cache !== null) return $cache;

    $query = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :dbname";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['dbname' => $dbname]);
    $tables = $stmt->fetchAll();

    $cache = [];
    foreach ($tables as $table) {
        $tableName = $table['TABLE_NAME'];
        $cache[$tableName] = [
            'columns' => getColumns($pdo, $tableName),
            'relations' => getRelations($pdo, $tableName)
        ];
    }
    return $cache;
}

// Função para obter colunas de uma tabela
function getColumns($pdo, $tableName) {
    $stmt = $pdo->query("DESCRIBE $tableName");
    return array_column($stmt->fetchAll(), 'Field');
}

// Função para obter relações
function getRelations($pdo, $tableName) {
    $query = "
        SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_NAME = :tableName AND REFERENCED_TABLE_NAME IS NOT NULL";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['tableName' => $tableName]);
    return $stmt->fetchAll();
}

// Função para executar consulta GET com suporte a variáveis
function executeGetQuery($pdo, $tableName, $params) {
    $tablesAndRelations = getTablesAndRelations($pdo, $GLOBALS['dbname']);

    if (!isset($tablesAndRelations[$tableName])) {
        return ['error' => 'Tabela não encontrada'];
    }

    $columns = $tablesAndRelations[$tableName]['columns'];
    $query = "SELECT " . implode(',', $columns) . " FROM $tableName";
    
    $whereConditions = [];
    $paramsToBind = [];
    foreach ($params as $param => $value) {
        // Verifica se o parâmetro passado é uma coluna válida da tabela
        if (in_array($param, $columns)) {
            $whereConditions[] = "$param = :$param";
            $paramsToBind[":$param"] = $value;
        }
    }

    if (!empty($whereConditions)) {
        $query .= " WHERE " . implode(' AND ', $whereConditions);
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($paramsToBind);
    return $stmt->fetchAll();
}

// Função para POST - Inserir dados
function insertData($pdo, $data) {
    $results = [];
    $pdo->beginTransaction();

    try {
        foreach ($data as $obj) {
            $table = $obj['table'];
            $columns = implode(",", $obj['columns']);
            $placeholders = implode(",", array_fill(0, count($obj['columns']), "?"));
            $values = $obj['values'];

            $query = "INSERT INTO $table ($columns) VALUES ($placeholders)";
            $stmt = $pdo->prepare($query);
            $stmt->execute($values);
            $results[] = ['id' => $pdo->lastInsertId(), 'status' => 'success'];
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $results[] = ['status' => 'failed', 'error' => $e->getMessage()];
    }

    return $results;
}

// Função para PUT - Atualizar dados
function updateData($pdo, $data) {
    $results = [];
    $pdo->beginTransaction();

    try {
        foreach ($data as $obj) {
            $table = $obj['table'];
            $field = $obj['field']; // Campo que será utilizado no WHERE
            $operator = $obj['comparator']; // Operador (ex.: '=', '>', '<', etc.)
            $value = $obj['value']; // Valor do campo para a busca

            // Atualizar as colunas e valores passados
            $setClause = implode(" = ?, ", $obj['columns']) . " = ?";
            $values = $obj['values']; // Valores a serem atualizados

            // Construir a query de UPDATE
            $query = "UPDATE $table SET $setClause WHERE $field $operator ?";
            $values[] = $value; // Adicionar o valor do WHERE ao final da lista de valores

            // Preparar e executar a consulta
            $stmt = $pdo->prepare($query);
            $stmt->execute($values);

            // Adicionar o resultado da execução
            $results[] = ['status' => 'success', 'affected_rows' => $stmt->rowCount()];
        }

        // Confirmar a transação
        $pdo->commit();
    } catch (Exception $e) {
        // Reverter a transação em caso de erro
        $pdo->rollBack();
        $results[] = ['status' => 'failed', 'error' => $e->getMessage()];
    }

    return $results;
}


// Função para DELETE - Deletar dados
function deleteData($pdo, $data) {
    $results = [];
    $pdo->beginTransaction();

    try {
        foreach ($data as $obj) {
            $table = $obj['table'];
            $field = $obj['field'];
            $operator = $obj['comparator'];
            $value = $obj['value'];

            $query = "DELETE FROM $table WHERE $field $operator ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$value]);
            $results[] = ['status' => 'success', 'affected_rows' => $stmt->rowCount()];
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $results[] = ['status' => 'failed', 'error' => $e->getMessage()];
    }

    return $results;
}

// Roteamento e manipulação de requisições
$requestUri = $_SERVER['REQUEST_URI'];
$uriParts = explode('?', trim($requestUri, '/'));
$path = $uriParts[0];
$method = $_SERVER['REQUEST_METHOD'];

// Lidar com a parte da URL que representa a tabela
if (!empty($path)) {
    $tableName = explode('/', $path)[0];

    try {
        switch ($method) {
            case 'GET':
                // Passa as variáveis da URL ($_GET) para a função de consulta
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
                throw new Exception('Método não suportado');
        }

        echo json_encode($result, JSON_PRETTY_PRINT);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()], JSON_PRETTY_PRINT);
    }
}