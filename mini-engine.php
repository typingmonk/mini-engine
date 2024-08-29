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
        mkdir('controllers');
        mkdir('libraries');
        mkdir('static');

        error_log("created directories: controllers, libraries, static");

        file_put_contents('index.php', <<<EOF
<?php
    define('MINI_ENGINE_LIBRARY', true);
    include(__DIR__ . '/mini-engine.php');
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
