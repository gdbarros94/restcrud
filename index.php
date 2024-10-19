<?php
// index.php

require_once 'config.php';
require_once 'modules/RequestRouter.php';
require_once 'modules/Authenticator.php';
require_once 'modules/DatabaseHandler.php';

$auth = new Authenticator($authType, $simpleKey, $multipleKeysFile, $ldapConfig);
$auth->authenticate();

$router = new RequestRouter($pdo, $dbname);
$router->route();
?>
