<?php

define('MINI_ENGINE_VERSION', '0.1.0');

class MiniEngine
{
    public static function dispatch($custom_function = null)
    {
        try {
            $controller_action_params = self::getControllerAndAction($custom_function);
            $controller = $controller_action_params[0];
            $action = $controller_action_params[1];
            $params = $controller_action_params[2] ?? [];
        } catch (Exception $e) {
            self::runControllerAction('error', 'error', [$e]);
        }

        self::runControllerAction($controller, $action, $params);
    }

    protected static function runControllerAction($controller, $action, $params)
    {
        $controller_class = ucfirst($controller) . 'Controller';
        $controller_file = 'controllers/' . $controller_class . '.php';
        if (!file_exists($controller_file)) {
            return self::runControllerAction('error', 'error', [new Exception("Controller not found: $controller")]);
        }

        include($controller_file);

        $controller_instance = new $controller_class();
        $action_method = $action . 'Action';
        if (!method_exists($controller_instance, $action_method)) {
            throw new Exception("Action not found: $action");
        }

        call_user_func_array([$controller_instance, $action_method], $params);
    }

    protected static function getControllerAndAction($custom_function)
    {
        if (!is_null($custom_function)) {
            $uri = $_SERVER['REQUEST_URI'];
            $result = $custom_function($uri);
            if (!is_null($result)) {
                return $result;
            }
        }

        $uri = $_SERVER['REQUEST_URI'];
        $uri = explode('?', $uri)[0];
        $uri = ltrim($uri, '/');
        $uri = explode('/', $uri);
        $controller = strtolower($uri[0] ?? 'index') ?: 'index';
        $action = strtolower($uri[1] ?? 'index') ?: 'index';
        return [$controller, $action];
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

MiniEngine::dispatch(function(\$uri){
    if (\$uri == '/robots.txt') {
        return ['index', 'robots'];
    }
    // default
    return null;
});

EOF
        );

        error_log("created index.php");

        // Create controllers/IndexController.php
        file_put_contents('controllers/IndexController.php', <<<EOF
<?php

class IndexController
{
    public function indexAction()
    {
        echo "Hello, Mini Engine!";
    }

    public function robotsAction()
    {
        header('Content-Type: text/plain');
        echo "#\\n";
    }
}

EOF
        );
        error_log("created controllers/IndexController.php");

        // Create controllers/ErrorController.php
        file_put_contents('controllers/ErrorController.php', <<<EOF
<?php

class ErrorController
{
    public function errorAction(\$error)
    {
        echo "Error: " . \$error->getMessage();
    }
}

EOF
        );
        error_log("created controllers/ErrorController.php");

        // Create .htaccess
        file_put_contents('.htaccess', <<<EOF
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.*)$ index.php [QSA,L]

EOF
        );
        error_log("created .htaccess");

        error_log("Mini Engine project initialized.");
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
