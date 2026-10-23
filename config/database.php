<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\PostgresConnection;

$mysqlSslCa = defined('Pdo\Mysql::ATTR_SSL_CA') ? \Pdo\Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here is you may specify which of the database connections below you wish
    | to use as your default connection for all database work. Of course
    | you may use many connections at once using the Database Manager.
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    | Of course, an example may be used for any database platform that is
    | supported by Laravel via the Database, PDO and PHP machinery.
    |
    | All database road is an array of options for each connection including
    | the "pdo" properties that a connection needs to be available.
    |
    | @link https://laravel.com/docs/database#configuration
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                $mysqlSslCa => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        // Koneksi baca ke database bridge H2H BTN Syariah (Go, va_billings).
        // Kredensial via H2H_DB_* di .env; sync polling di BsnH2hService.
        'mysql_h2h' => [
            'driver' => 'mysql',
            'host' => env('H2H_DB_HOST', '127.0.0.1'),
            'port' => env('H2H_DB_PORT', '3306'),
            'database' => env('H2H_DB_DATABASE', 'sikeudb'),
            'username' => env('H2H_DB_USERNAME', 'root'),
            'password' => env('H2H_DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ],

        // Koneksi ke Database SIK Legacy (MySQL terpisah, beda server)
        // Gunakan read-only user jika memungkinkan
        'mysql_sik_legacy' => [
            'driver' => 'mysql',
            'host' => env('SIK_LEGACY_DB_HOST', '192.168.5.167'),
            'port' => env('SIK_LEGACY_DB_PORT', '3306'),
            'database' => env('SIK_LEGACY_DB_DATABASE', 'indonusa'),
            'username' => env('SIK_LEGACY_DB_USERNAME', 'sistem'),
            'password' => env('SIK_LEGACY_DB_PASSWORD', 'random113u'),
            'unix_socket' => env('SIK_LEGACY_DB_SOCKET', ''),
            'charset' => env('SIK_LEGACY_DB_CHARSET', 'latin1'),
            'collation' => env('SIK_LEGACY_DB_COLLATION', 'latin1_swedish_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => false,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                $mysqlSslCa => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        // Koneksi ke Database PresensiV2 (MySQL terpisah, server berbeda).
        // Dipakai sebagai jalur "direct-DB" integrasi LMS -> PresensiV2
        // (lihat config/lms_integration.php). Bila environment ini belum
        // dikonfigurasi, integrasi otomatis berstatus "skipped".
        'presensiv2' => [
            'driver' => 'mysql',
            'host' => env('PRESENSI_DB_HOST', '127.0.0.1'),
            'port' => env('PRESENSI_DB_PORT', '3306'),
            'database' => env('PRESENSI_DB_DATABASE', 'presensi'),
            'username' => env('PRESENSI_DB_USERNAME', 'root'),
            'password' => env('PRESENSI_DB_PASSWORD', ''),
            'unix_socket' => env('PRESENSI_DB_SOCKET', ''),
            'charset' => env('PRESENSI_DB_CHARSET', 'utf8mb4'),
            'collation' => env('PRESENSI_DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => false,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                $mysqlSslCa => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mysql_demo' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST_DEMO', env('DB_HOST', '127.0.0.1')),
            'port' => env('DB_PORT_DEMO', env('DB_PORT', '3306')),
            'database' => env('DB_DATABASE_DEMO', 'laravel'),
            'username' => env('DB_USERNAME_DEMO', env('DB_USERNAME', 'root')),
            'password' => env('DB_PASSWORD_DEMO', env('DB_PASSWORD', '')),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                $mysqlSslCa => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                $mysqlSslCa => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all migrations that have been run for your
    | application. Using this information, we can determine which of the
    | migrations on disk actually have been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced cable server that's usable
    | for many caching applications. By default, Laravel is configured to use
    | the redis cable server for a number of caching and session drivers.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '1'),
        ],

    ],

];
