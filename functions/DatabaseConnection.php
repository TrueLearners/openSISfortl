<?php
/**
 * Shared MySQL connection helper for openSIS.
 *
 * Supports the standard installer Data.php configuration and Render/TiDB
 * environment variables. TiDB Cloud public endpoints require TLS; when TLS is
 * enabled we use the operating system CA bundle unless DB_SSL_CA is supplied.
 */
if (!function_exists('opensis_db_config')) {
    function opensis_db_config()
    {
        $data = array(
            'host' => getenv('DB_HOST') ?: ($GLOBALS['DatabaseServer'] ?? ''),
            'port' => getenv('DB_PORT') ?: ($GLOBALS['DatabasePort'] ?? 3306),
            'database' => getenv('DB_DATABASE') ?: ($GLOBALS['DatabaseName'] ?? ''),
            'username' => getenv('DB_USERNAME') ?: ($GLOBALS['DatabaseUsername'] ?? ''),
            'password' => getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : ($GLOBALS['DatabasePassword'] ?? ''),
            'ssl' => getenv('DB_SSL') !== false ? filter_var(getenv('DB_SSL'), FILTER_VALIDATE_BOOLEAN) : null,
            'ca' => getenv('DB_SSL_CA') ?: '',
        );

        $data['port'] = (int) $data['port'];
        if ($data['ssl'] === null) {
            $data['ssl'] = ($data['port'] === 4000);
        }
        return $data;
    }

    function opensis_mysqli_connect($databaseOverride = null)
    {
        $cfg = opensis_db_config();
        $database = $databaseOverride !== null ? $databaseOverride : $cfg['database'];
        $mysqli = mysqli_init();
        if (!$mysqli) {
            throw new RuntimeException('Unable to initialize MySQL connection.');
        }

        if ($cfg['ssl']) {
            $ca = $cfg['ca'];
            if ($ca !== '' && !is_file($ca)) {
                throw new RuntimeException('DB_SSL_CA is set but the CA certificate file does not exist.');
            }
            $caPath = $ca !== '' ? $ca : (is_file('/etc/ssl/certs/ca-certificates.crt') ? '/etc/ssl/certs/ca-certificates.crt' : null);
            if ($caPath !== null) {
                mysqli_ssl_set($mysqli, null, null, $caPath, null, null);
            } elseif (defined('MYSQLI_CLIENT_SSL')) {
                // Require TLS even when the system OpenSSL trust store is used.
                mysqli_options($mysqli, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
            }
            $flags = MYSQLI_CLIENT_SSL;
        } else {
            $flags = 0;
        }

        $ok = mysqli_real_connect(
            $mysqli,
            $cfg['host'],
            $cfg['username'],
            $cfg['password'],
            $database ?: null,
            $cfg['port'],
            null,
            $flags
        );
        if (!$ok) {
            throw new RuntimeException('Database connection failed: ' . mysqli_connect_error());
        }
        $mysqli->set_charset('utf8');
        $mysqli->query("SET SESSION sql_mode = ''");
        return $mysqli;
    }
}
