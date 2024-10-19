<?php
// db_functions.php

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

// Função para executar consulta GET
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
            $field = $obj['field'];
            $operator = $obj['comparator'];
            $value = $obj['value'];

            $setClause = implode(" = ?, ", $obj['columns']) . " = ?";
            $values = $obj['values'];

            $query = "UPDATE $table SET $setClause WHERE $field $operator ?";
            $values[] = $value;

            $stmt = $pdo->prepare($query);
            $stmt->execute($values);
            $results[] = ['status' => 'success', 'affected_rows' => $stmt->rowCount()];
        }

        $pdo->commit();
    } catch (Exception $e) {
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
