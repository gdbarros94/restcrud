<?php
// auth_module.php

class AuthModule {
    private $authType;
    private $simpleKey;
    private $multipleKeys;
    private $ldapConfig;

    public function __construct($authType = 'none', $simpleKey = '', $multipleKeysFile = '', $ldapConfig = []) {
        $this->authType = $authType;
        $this->simpleKey = $simpleKey;
        $this->ldapConfig = $ldapConfig;

        if ($authType === 'multiple' && file_exists($multipleKeysFile)) {
            $this->multipleKeys = json_decode(file_get_contents($multipleKeysFile), true);
        }
    }

    public function authenticate($providedKey, $username = '', $password = '') {
        switch ($this->authType) {
            case 'simple':
                return $providedKey === $this->simpleKey;
            case 'multiple':
                return in_array($providedKey, $this->multipleKeys);
            case 'ldap':
                return $this->authenticateLdap($username, $password);
            default:
                return true; // Sem autenticação
        }
    }

    private function authenticateLdap($username, $password) {
        if (empty($this->ldapConfig)) {
            return false;
        }

        $ldapConn = ldap_connect($this->ldapConfig['host'], $this->ldapConfig['port']);
        if (!$ldapConn) {
            return false;
        }

        ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldapConn, LDAP_OPT_REFERRALS, 0);

        $bind = @ldap_bind($ldapConn, $username, $password);
        if (!$bind) {
            return false;
        }

        $filter = "({$this->ldapConfig['username_attribute']}=$username)";
        $search = ldap_search($ldapConn, $this->ldapConfig['base_dn'], $filter);
        $entries = ldap_get_entries($ldapConn, $search);

        ldap_unbind($ldapConn);

        return $entries['count'] > 0;
    }

    public function testLdapConnection($host, $port, $base_dn, $username_attribute, $admin_username, $admin_password) {
        $ldapConn = ldap_connect($host, $port);
        if (!$ldapConn) {
            return ['status' => 'error', 'message' => 'Falha ao conectar ao servidor LDAP'];
        }

        ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldapConn, LDAP_OPT_REFERRALS, 0);

        $bind = @ldap_bind($ldapConn, $admin_username, $admin_password);
        if (!$bind) {
            return ['status' => 'error', 'message' => 'Falha na autenticação com as credenciais fornecidas'];
        }

        $search = ldap_search($ldapConn, $base_dn, "(objectClass=*)");
        if (!$search) {
            return ['status' => 'error', 'message' => 'Falha ao realizar busca no LDAP'];
        }

        $entries = ldap_get_entries($ldapConn, $search);
        ldap_unbind($ldapConn);

        return [
            'status' => 'success',
            'message' => 'Conexão LDAP bem-sucedida',
            'user_count' => $entries['count']
        ];
    }
}

// Endpoint para testar a conexão LDAP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'test_ldap') {
    header('Content-Type: application/json');
    
    $data = json_decode(file_get_contents('php://input'), true);
    $requiredFields = ['host', 'port', 'base_dn', 'username_attribute', 'admin_username', 'admin_password'];
    
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            echo json_encode(['status' => 'error', 'message' => "Campo obrigatório ausente: $field"]);
            exit;
        }
    }

    $auth = new AuthModule();
    $result = $auth->testLdapConnection(
        $data['host'],
        $data['port'],
        $data['base_dn'],
        $data['username_attribute'],
        $data['admin_username'],
        $data['admin_password']
    );

    echo json_encode($result);
    exit;
}
?>