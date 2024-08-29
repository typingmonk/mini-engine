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
        $cmd = isset($argv[1]) ? $argv[1] : null;
        switch ($cmd) {
            case 'init':
                self::cmd_init();
                break;
            default:
                self::cmd_help();
                break;
        }
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
