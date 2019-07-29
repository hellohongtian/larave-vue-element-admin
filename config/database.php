<?php
$redisConfig = explode(':', $_SERVER['SITE_REDIS_SERVER']);
$_SERVER['FAST_REDIS_HOSTNAME'] = $redisConfig[0];
$_SERVER['FAST_REDIS_PORT'] = $redisConfig[1];

$simulationServerIps = [
    '172.16.10.151',
    '172.16.10.150'
];

$simulationServerDBPrefix = 'PRE_';

if (isset($_SERVER['SERVER_ADDR']) &&  in_array($_SERVER['SERVER_ADDR'], $simulationServerIps)) {
    define('FIN_SIMULATION_ENV', $simulationServerDBPrefix);
} else {
    define('FIN_SIMULATION_ENV', '');
}

return [

    /*
    |--------------------------------------------------------------------------
    | PDO Fetch Style
    |--------------------------------------------------------------------------
    |
    | By default, database results will be returned as instances of the PHP
    | stdClass object; however, you may desire to retrieve records in an
    | array format for simplicity. Here you can tweak the fetch style.
    |
    */

    'fetch' => PDO::FETCH_OBJ,

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for all database work. Of course
    | you may use many connections at once using the Database library.
    |
    */

    'default' => env('DB_CONNECTION', 'mysql.video_visa'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    | Of course, examples of configuring each database platform that is
    | supported by Laravel is shown below to make development simple.
    |
    |
    | All database work in Laravel is done through the PHP PDO facilities
    | so make sure you have the driver for your particular database of
    | choice installed on your machine before you begin development.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
        ],

//        'mysql' => [
//            'driver' => 'mysql',
//            'host' => env('DB_HOST', '127.0.0.1'),
//            'port' => env('DB_PORT', '3306'),
//            'database' => env('DB_DATABASE', 'forge'),
//            'username' => env('DB_USERNAME', 'forge'),
//            'password' => env('DB_PASSWORD', ''),
//            'charset' => 'utf8',
//            'collation' => 'utf8_unicode_ci',
//            'prefix' => '',
//            'strict' => true,
//            'engine' => null,
//        ],

        'mysql.video_visa' => [
            'read' => [
                'host'      => $_SERVER['DB_VIDEO_VISA_HOST'],
                'port'      => $_SERVER['DB_VIDEO_VISA_PORT'],
                'username'  => $_SERVER['DB_VIDEO_VISA_USER'],
                'password'  => $_SERVER['DB_VIDEO_VISA_PASS'],
                'database'  => $_SERVER['DB_VIDEO_VISA_NAME'],
            ],
            'write' => [
                //rm-2ze1p2cm5474t83bnrw.mysql.rds.aliyuncs.com  rm-2ze1p2cm5474t83bn.mysql.rds.aliyuncs.com
                 'host'      => $_SERVER['DB_VIDEO_VISA_HOST_W'],
//                'host'      => is_production_env()?'rm-2ze1p2cm5474t83bn.mysql.rds.aliyuncs.com':$_SERVER['DB_VIDEO_VISA_HOST_W'],
                'port'      => $_SERVER['DB_VIDEO_VISA_PORT_W'],
                'username'  => $_SERVER['DB_VIDEO_VISA_USER_W'],
                'password'  => $_SERVER['DB_VIDEO_VISA_PASS_W'],
                'database'  => $_SERVER['DB_VIDEO_VISA_NAME_W'],
            ],
//            'read' => [
//                'host'      => $_SERVER['DB_VIDEO_VISA_TEST_HOST'],
//                'port'      => $_SERVER['DB_VIDEO_VISA_TEST_PORT'],
//                'username'  => $_SERVER['DB_VIDEO_VISA_TEST_USER'],
//                'password'  => $_SERVER['DB_VIDEO_VISA_TEST_PASS'],
//                'database'  => $_SERVER['DB_VIDEO_VISA_TEST_NAME'],
//            ],
//            'write' => [
//                'host'      => $_SERVER['DB_VIDEO_VISA_TEST_HOST_W'],
//                'port'      => $_SERVER['DB_VIDEO_VISA_TEST_PORT_W'],
//                'username'  => $_SERVER['DB_VIDEO_VISA_TEST_USER_W'],
//                'password'  => $_SERVER['DB_VIDEO_VISA_TEST_PASS_W'],
//                'database'  => $_SERVER['DB_VIDEO_VISA_TEST_NAME_W'],
//            ],
            'driver'    => 'mysql',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
        ],


        'mysql.xin' => [
            'write' => [
                'host' =>  $_SERVER['DB_XIN_HOST'],
                'port' => $_SERVER['DB_XIN_PORT'],
                'database' => $_SERVER['DB_XIN_NAME'],
                'username' => $_SERVER['DB_XIN_USER'],
                'password' => $_SERVER['DB_XIN_PASS'],
            ],
            'read' => [
                'host' =>  $_SERVER['DB_XIN_HOST_W'],
                'port' => $_SERVER['DB_XIN_PORT_W'],
                'database' => $_SERVER['DB_XIN_NAME_W'],
                'username' => $_SERVER['DB_XIN_USER_W'],
                'password' => $_SERVER['DB_XIN_PASS_W'],
            ],

            'driver'    => 'mysql',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
        ],

        'mysql.xin_credit' => [
            'read' => [
                'host'      => $_SERVER[FIN_SIMULATION_ENV.'DB_CREDIT_HOST'],
                'port'      => $_SERVER[FIN_SIMULATION_ENV.'DB_CREDIT_PORT'],
                'username'  => $_SERVER[FIN_SIMULATION_ENV.'DB_CREDIT_USER'],
                'password'  => $_SERVER[FIN_SIMULATION_ENV.'DB_CREDIT_PASS'],
                'database'  => $_SERVER[FIN_SIMULATION_ENV.'DB_CREDIT_NAME'],
            ],
            'write' => [
                'host'      => $_SERVER[FIN_SIMULATION_ENV.'DB_CREDIT_HOST_W'],
                'port'      => $_SERVER[FIN_SIMULATION_ENV.'DB_CREDIT_PORT_W'],
                'username'  => $_SERVER[FIN_SIMULATION_ENV.'DB_CREDIT_USER_W'],
                'password'  => $_SERVER[FIN_SIMULATION_ENV.'DB_CREDIT_PASS_W'],
                'database'  => $_SERVER[FIN_SIMULATION_ENV.'DB_CREDIT_NAME_W'],
            ],
            'driver'    => 'mysql',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
        ],

        'mysql.newcar' => [
            'write' => [
                'host' =>  $_SERVER['DB_NEWCAR_HOST'],
                'port' => $_SERVER['DB_NEWCAR_PORT'],
                'database' => $_SERVER['DB_NEWCAR_NAME'],
                'username' => $_SERVER['DB_NEWCAR_USER'],
                'password' => $_SERVER['DB_NEWCAR_PASS'],
            ],
            'read' => [
                'host' =>  $_SERVER['DB_NEWCAR_HOST'],
                'port' => $_SERVER['DB_NEWCAR_PORT'],
                'database' => $_SERVER['DB_NEWCAR_NAME'],
                'username' => $_SERVER['DB_NEWCAR_USER'],
                'password' => $_SERVER['DB_NEWCAR_PASS'],
            ],
            'driver'    => 'mysql',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
        ],

        'mysql.finance' => [
            'read' => [
                'host'      => $_SERVER['DB_FINANCE_HOST'],
                'port'      => $_SERVER['DB_FINANCE_PORT'],
                'username'  => $_SERVER['DB_FINANCE_USER'],
                'password'  => $_SERVER['DB_FINANCE_PASS'],
                'database'  => $_SERVER['DB_FINANCE_NAME'],
            ],
            'write' => [
                'host'      => $_SERVER['DB_FINANCE_HOST'],
                'port'      => $_SERVER['DB_FINANCE_PORT'],
                'username'  => $_SERVER['DB_FINANCE_USER'],
                'password'  => $_SERVER['DB_FINANCE_PASS'],
                'database'  => $_SERVER['DB_FINANCE_NAME'],
            ],

            'driver'    => 'mysql',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
        ],

        'mysql.xinpay' => [
            'read'  => [
                'host'      => $_SERVER['DB_XIN_PAY_HOST_R'],
                'port'      => $_SERVER['DB_XIN_PAY_PORT_R'],
                'database'  => $_SERVER['DB_XIN_PAY_NAME_R'],
                'username'  => $_SERVER['DB_XIN_PAY_USER_R'],
                'password'  => $_SERVER['DB_XIN_PAY_PASS_R'],
            ],
            'write' => [
                'host'      => $_SERVER['DB_XIN_PAY_HOST_R'],
                'port'      => $_SERVER['DB_XIN_PAY_PORT_R'],
                'database'  => $_SERVER['DB_XIN_PAY_NAME_R'],
                'username'  => $_SERVER['DB_XIN_PAY_USER_R'],
                'password'  => $_SERVER['DB_XIN_PAY_PASS_R'],
            ],
            'driver'    => 'mysql',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
        ],


        'mysql.risk_stat' => [
            'read' => [
                'host' => $_SERVER['DB_RISK_HOST'],
                'port' => $_SERVER['DB_RISK_PORT'],
                'username' => $_SERVER['DB_RISK_USER'],
                'password' => $_SERVER['DB_RISK_PASS'],
                'database' => $_SERVER['DB_RISK_NAME'],
            ],
            'write' => [
                'host' => $_SERVER['DB_RISK_HOST'],
                'port' => $_SERVER['DB_RISK_PORT'],
                'username' => $_SERVER['DB_RISK_USER'],
                'password' => $_SERVER['DB_RISK_PASS'],
                'database' => $_SERVER['DB_RISK_NAME'],
            ],
            'driver' => 'mysql',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
            'prefix' => env('DB_PREFIX', ''),
            'timezone' => env('DB_TIMEZONE', '+8:00'),
            'strict' => false,
        ],

        'mysql.sys_finance' => [
            'read' => [
                'host'      => $_SERVER['DB_SYS_FINANCE_HOST'],
                'port'      => $_SERVER['DB_SYS_FINANCE_PORT'],
                'username'  => $_SERVER['DB_SYS_FINANCE_USER'],
                'password'  => $_SERVER['DB_SYS_FINANCE_PASS'],
                'database'  => $_SERVER['DB_SYS_FINANCE_NAME'],
            ],
            'write' => [
                'host'      => $_SERVER['DB_SYS_FINANCE_HOST'],
                'port'      => $_SERVER['DB_SYS_FINANCE_PORT'],
                'username'  => $_SERVER['DB_SYS_FINANCE_USER'],
                'password'  => $_SERVER['DB_SYS_FINANCE_PASS'],
                'database'  => $_SERVER['DB_SYS_FINANCE_NAME'],
            ],

            'driver'    => 'mysql',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run in the database.
    |
    */

    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer set of commands than a typical key-value systems
    | such as APC or Memcached. Laravel makes it easy to dig right in.
    |
    */

    'redis' => [
        'cluster' => false,
        'default' => [
            'host' => !is_dev_env()? $_SERVER['FAST_REDIS_HOSTNAME']:'127.0.0.1',
            'port' => $_SERVER['FAST_REDIS_PORT'],
            'database' => 0,
        ],

    ],

    'ldap' => [
        'host' => $_SERVER['SITE_LDAP_HOST'],
        'port' => $_SERVER['SITE_LDAP_PORT'],
        'user' => $_SERVER['SITE_LDAP_USER'],
        'pass' => $_SERVER['SITE_LDAP_PASS'],
        'dn' => 'OU=优信拍,DC=uxin,DC=youxinpai,DC=com',
    ],

];
