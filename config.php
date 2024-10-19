<?php
// config.php

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
