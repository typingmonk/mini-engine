<?php

define('MINI_ENGINE_VERSION', '0.1.0');

class MiniEngine
{
    public static function dispatch()
    {
    }
}

class MiniEngineCLI
{
    public static function dispatch()
    {
        $cmd = $_SERVER['argv'][1] ?? null;
        switch ($cmd) {
            case 'init':
                self::cmd_init();
                break;
            default:
                self::cmd_help();
                break;
        }
    }

    public static function cmd_init()
    {
        // Create directories
        $directories = [
            'controllers',
            'libraries',
            'static',
        ];
        foreach ($directories  as $dir) {
            if (file_exists($dir)) {
                continue;
            }
            mkdir($dir);
        }
        error_log("created directories: " . implode(', ', $directories));

        // Create init.inc.php
        file_put_contents('init.inc.php', <<<EOF
<?php
    define('MINI_ENGINE_LIBRARY', true);
    include(__DIR__ . '/mini-engine.php');
    if (file_exists(__DIR__ . '/config.inc.php')) {
        include(__DIR__ . '/config.inc.php');
    }
EOF
        );
        error_log("created init.inc.php");

        // Create config.sample.inc.php
        file_put_contents('config.sample.inc.php', <<<EOF
<?php
    putenv('APP_NAME', 'Mini Engine sample application');
EOF
        );
        error_log("created config.sample.inc.php");

        // Create .gitignore
        file_put_contents('.gitignore', <<<EOF
config.inc.php

EOF
        );
        error_log("created .gitignore");

        // Create index.php
        file_put_contents('index.php', <<<EOF
<?php
    include(__DIR__ . '/init.inc.php');

    MiniEngine::dispatch(function(){
    });
EOF
        );

        error_log("created index.php");

        file_put_contents('controllers/IndexController.php', <<<EOF
<?php

class IndexController
{
    public function indexAction()
    {
        echo "Hello, Mini Engine!";
    }
}
EOF
        );

        error_log("created controllers/IndexController.php");
    }

    public static function cmd_help()
    {
        echo "Usage: php mini-engine.php <command>\n";
        echo "Commands:\n";
        echo "  init    Initialize a new Mini Engine project\n";
    }
}

if (!defined('MINI_ENGINE_LIBRARY')) {
    MiniEngineCLI::dispatch();
}
