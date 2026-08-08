<?php
// site root path
define('__TYPECHO_ROOT_DIR__', dirname(__FILE__));

// plugin directory (relative path)
define('__TYPECHO_PLUGIN_DIR__', '/usr/plugins');

// theme directory (relative path)
define('__TYPECHO_THEME_DIR__', '/usr/themes');

// admin directory (relative path)
define('__TYPECHO_ADMIN_DIR__', '/admin/');

// register autoload
require_once __TYPECHO_ROOT_DIR__ . '/var/Typecho/Common.php';

// init
\Typecho\Common::init();

function typecho_build_db_connection(): array
{
    $adapter = 'Pdo_SQLite';
    $prefix = 'typecho_';
    $dbConfig = [];

    $databaseUrl = getenv('DATABASE_URL') ?: getenv('POSTGRES_URL') ?: getenv('POSTGRES_PRISMA_URL');
    if (!empty($databaseUrl)) {
        $parsedUrl = parse_url($databaseUrl);

        if (!empty($parsedUrl['host']) && !empty($parsedUrl['path'])) {
            $adapter = 'Pdo_Pgsql';
            $sslmode = 'disable';
            if (isset($parsedUrl['query'])) {
                parse_str($parsedUrl['query'], $queryParts);
                if (!empty($queryParts['sslmode'])) {
                    $sslmode = $queryParts['sslmode'];
                }
            }

            if (str_contains($parsedUrl['host'], 'neon.tech') || str_contains($databaseUrl, 'neon.tech')) {
                $sslmode = 'require';
            }

            $dbConfig = [
                'host' => $parsedUrl['host'],
                'port' => !empty($parsedUrl['port']) ? (int) $parsedUrl['port'] : 5432,
                'user' => isset($parsedUrl['user']) ? urldecode($parsedUrl['user']) : '',
                'password' => isset($parsedUrl['pass']) ? urldecode($parsedUrl['pass']) : '',
                'database' => ltrim($parsedUrl['path'], '/'),
                'charset' => 'utf8',
                'sslmode' => $sslmode,
            ];
        }
    }

    if (empty($dbConfig)) {
        $postgresHost = getenv('PGHOST') ?: getenv('POSTGRES_HOST');
        $postgresUser = getenv('PGUSER') ?: getenv('POSTGRES_USER');
        $postgresPassword = getenv('PGPASSWORD') ?: getenv('POSTGRES_PASSWORD');
        $postgresDatabase = getenv('PGDATABASE') ?: getenv('POSTGRES_DATABASE');

        if (!empty($postgresHost) && !empty($postgresUser) && !empty($postgresPassword) && !empty($postgresDatabase)) {
            $adapter = 'Pdo_Pgsql';
            $dbConfig = [
                'host' => $postgresHost,
                'port' => 5432,
                'user' => $postgresUser,
                'password' => $postgresPassword,
                'database' => $postgresDatabase,
                'charset' => 'utf8',
                'sslmode' => 'require',
            ];
        }
    }

    if (empty($dbConfig)) {
        $dbConfig = [
            'file' => sys_get_temp_dir() . '/typecho-' . md5(__TYPECHO_ROOT_DIR__) . '.db',
        ];
    }

    return [
        'adapter' => $adapter,
        'prefix' => $prefix,
        'dbConfig' => $dbConfig,
    ];
}

try {
    $connection = typecho_build_db_connection();
    $db = new \Typecho\Db($connection['adapter'], $connection['prefix']);
    $db->addServer($connection['dbConfig'], \Typecho\Db::READ | \Typecho\Db::WRITE);
    \Typecho\Db::set($db);
} catch (\Throwable $e) {
    $db = new \Typecho\Db('Pdo_SQLite', 'typecho_');
    $db->addServer([
        'file' => sys_get_temp_dir() . '/typecho-' . md5(__TYPECHO_ROOT_DIR__) . '.db',
    ], \Typecho\Db::READ | \Typecho\Db::WRITE);
    \Typecho\Db::set($db);
}
