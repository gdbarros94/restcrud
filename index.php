<?php
// index.php

// Incluir configurações e módulos
require_once 'config.php';
require_once 'modules/auth_module.php';
require_once 'modules/db_functions.php';
require_once 'modules/routes.php';

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

// Iniciar roteamento das requisições
routeRequest($pdo, $dbname);
