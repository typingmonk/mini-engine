<?php

define('MINI_ENGINE_VERSION', '0.1.0');

class MiniEngine
{
    public static function getRoot()
    {
        if (defined('MINI_ENGINE_ROOT')) {
            return MINI_ENGINE_ROOT;
        }
        return __DIR__;
    }

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
        $controller_file = self::getRoot() . '/controllers/' . $controller_class . '.php';
        if (!file_exists($controller_file)) {
            return self::runControllerAction('error', 'error', [new Exception("Controller not found: $controller")]);
        }

        if (!class_exists($controller_class)) {
            include($controller_file);
        }

        $controller_instance = new $controller_class();
        $action_method = $action . 'Action';
        if (!method_exists($controller_instance, $action_method)) {
            throw new Exception("Action not found: $action");
        }

        try {
            call_user_func_array([$controller_instance, 'init'], $params);
            call_user_func_array([$controller_instance, $action_method], $params);
            $view_file = self::getRoot() . '/views/' . $controller . '/' . $action . '.php';
            echo $controller_instance->draw($view_file);
        } catch (MiniEngine_Controller_NoView $e) {
            // do nothing
        } catch (Exception $e) {
            self::runControllerAction('error', 'error', [$e]);
        }
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

class MiniEngine_Controller_NoView extends Exception
{
}

class MiniEngine_Controller_ViewObject
{
    protected $_data = [];

    public function __set($name, $value)
    {
        $this->_data[$name] = $value;
    }

    public function __get($name)
    {
        return $this->_data[$name] ?? null;
    }

    public function __isset($name)
    {
        return isset($this->_data[$name]);
    }

    public function partial($file, $data = null)
    {
        if ($data instanceof MiniEngine_Controller_ViewObject) {
            $data = $data->_data;
        } else {
            $data = (array) $data;
        }

        $original_data = $this->_data;
        $this->_data = $data;

        if (!file_exists($file)) {
            if (file_exists(MiniEngine::getRoot() . "/views/{$file}.php")) {
                $file = MiniEngine::getRoot() . "/views/{$file}.php";
            } else {
                throw new Exception("Partial file not found: $file");
            }
        }

        ob_start();
        include($file);
        $content = ob_get_clean();

        $this->_data = $original_data;
        return $content;
    }

    public function escape($string)
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

class MiniEngine_Controller
{
    protected $view;

    public function __construct()
    {
        $this->view = new MiniEngine_Controller_ViewObject();
    }

    public function init()
    {
    }

    public function draw($view_file)
    {
        if (!file_exists($view_file)) {
            throw new Exception("View file not found: $view_file");
        }
        return $this->view->partial($view_file, $this->view);
    }

    public function json($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        return $this->noview();
    }

    public function noview()
    {
        throw new MiniEngine_Controller_NoView();
    }

    public function redirect($uri, $code = 302)
    {
        header("Location: $uri", true, $code);
        return $this->noview();
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
            'views',
            'views/index',
            'views/common',
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
define('MINI_ENGINE_ROOT', __DIR__);
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

class IndexController extends MiniEngine_Controller
{
    public function indexAction()
    {
        \$this->view->app_name = getenv('APP_NAME');
    }

    public function robotsAction()
    {
        header('Content-Type: text/plain');
        echo "#\\n";
        return \$this->noview();
    }
}

EOF
        );
        error_log("created controllers/IndexController.php");

        // Create controllers/ErrorController.php
        file_put_contents('controllers/ErrorController.php', <<<EOF
<?php

class ErrorController extends MiniEngine_Controller
{
    public function errorAction(\$error)
    {
        echo "Error: " . \$error->getMessage();
        return \$this->noview();
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

        // Create /views/common/header.php
        file_put_contents('views/common/header.php', <<<EOF
<!DOCTYPE html>
<html>
<head>
<title><?= \$this->escape(\$this->app_name) ?></title>
</head>
<body>

EOF
        );
        error_log("created views/common/header.php");

        // Create /views/common/footer.php
        file_put_contents('views/common/footer.php', <<<EOF
</body>
</html>

EOF
        );
        error_log("created views/common/footer.php");

        // Create /views/index/index.php
        file_put_contents('views/index/index.php', <<<EOF
<?= \$this->partial('common/header') ?>
This is Index page
<?= \$this->partial('common/footer') ?>

EOF
        );
        error_log("created views/index/index.php");

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
