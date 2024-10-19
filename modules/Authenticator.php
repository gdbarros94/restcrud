<?php
// Authenticator.php

class Authenticator {
    private $authModule;

    public function __construct($authType, $simpleKey, $multipleKeysFile, $ldapConfig) {
        $this->authModule = new AuthModule($authType, $simpleKey, $multipleKeysFile, $ldapConfig);
    }

    public function authenticate() {
        if ($_SERVER['PHP_AUTH_USER'] ?? false && $_SERVER['PHP_AUTH_PW'] ?? false) {
            if (!$this->authModule->authenticate('', $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {
                $this->denyAccess();
            }
        } elseif ($_SERVER['HTTP_X_API_KEY'] ?? false) {
            if (!$this->authModule->authenticate($_SERVER['HTTP_X_API_KEY'])) {
                $this->denyAccess();
            }
        } else {
            $this->denyAccess();
        }
    }

    private function denyAccess() {
        header('HTTP/1.0 401 Unauthorized');
        die(json_encode(['error' => 'Não autorizado']));
    }
}
