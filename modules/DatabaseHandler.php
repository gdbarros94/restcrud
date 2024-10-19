<?php
// DatabaseHandler.php

class DatabaseHandler {
    private $pdo;

    public function __construct($host, $dbname, $username, $password) {
        try {
            $this->pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Erro na conexão: ' . $e->getMessage()]));
        }
    }

    // Método para obter tabelas e relações
    public function getTablesAndRelations($dbname) {
        static $cache = null;
        if ($cache !== null) return $cache;

        $query = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :dbname";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute(['dbname' => $dbname]);
        $tables = $stmt->fetchAll();

        $cache = [];
        foreach ($tables as $table) {
            $tableName = $table['TABLE_NAME'];
            $cache[$tableName] = [
                'columns' => $this->getColumns($tableName),
                'relations' => $this->getRelations($tableName)
            ];
        }
        return $cache;
    }

    // Método para obter colunas de uma tabela
    public function getColumns($tableName) {
        $stmt = $this->pdo->query("DESCRIBE $tableName");
        return array_column($stmt->fetchAll(), 'Field');
    }

    // Método para obter relações de uma tabela
    public function getRelations($tableName) {
        $query = "
            SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_NAME = :tableName AND REFERENCED_TABLE_NAME IS NOT NULL";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute(['tableName' => $tableName]);
        return $stmt->fetchAll();
    }

    // Método para executar consulta GET
    public function executeGetQuery($tableName, $params) {
        $tablesAndRelations = $this->getTablesAndRelations($GLOBALS['dbname']);

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

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($paramsToBind);
        return $stmt->fetchAll();
    }

    // Método para inserir dados (POST)
    public function insertData($data) {
        $results = [];
        $this->pdo->beginTransaction();

        try {
            foreach ($data as $obj) {
                $table = $obj['table'];
                $columns = implode(",", $obj['columns']);
                $placeholders = implode(",", array_fill(0, count($obj['columns']), "?"));
                $values = $obj['values'];

                $query = "INSERT INTO $table ($columns) VALUES ($placeholders)";
                $stmt = $this->pdo->prepare($query);
                $stmt->execute($values);
                $results[] = ['id' => $this->pdo->lastInsertId(), 'status' => 'success'];
            }
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $results[] = ['status' => 'failed', 'error' => $e->getMessage()];
        }

        return $results;
    }

    // Método para atualizar dados (PUT)
    public function updateData($data) {
        $results = [];
        $this->pdo->beginTransaction();

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

                $stmt = $this->pdo->prepare($query);
                $stmt->execute($values);
                $results[] = ['status' => 'success', 'affected_rows' => $stmt->rowCount()];
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $results[] = ['status' => 'failed', 'error' => $e->getMessage()];
        }

        return $results;
    }

    // Método para deletar dados (DELETE)
    public function deleteData($data) {
        $results = [];
        $this->pdo->beginTransaction();

        try {
            foreach ($data as $obj) {
                $table = $obj['table'];
                $field = $obj['field'];
                $operator = $obj['comparator'];
                $value = $obj['value'];

                $query = "DELETE FROM $table WHERE $field $operator ?";
                $stmt = $this->pdo->prepare($query);
                $stmt->execute([$value]);
                $results[] = ['status' => 'success', 'affected_rows' => $stmt->rowCount()];
            }
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $results[] = ['status' => 'failed', 'error' => $e->getMessage()];
        }

        return $results;
    }
}
?>
