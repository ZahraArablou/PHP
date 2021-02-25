<?php

session_start();

require_once 'vendor/autoload.php';

use Slim\Http\Request;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

const ROWS_PER_PAGE = 6;


// create a log channel
$log = new Logger('main');
$log->pushHandler(new StreamHandler('logs/everything.log', Logger::DEBUG));
$log->pushHandler(new StreamHandler('logs/errors.log', Logger::ERROR));

// always include authentication info and client's IP address in the log
$log->pushProcessor(function ($record) {
    $record['extra']['user'] = isset($_SESSION['user']) ? $_SESSION['user']['username'] : '=anonymous=';
    $record['extra']['ip'] = $_SERVER['REMOTE_ADDR'];
    return $record;
});



if (strpos($_SERVER['HTTP_HOST'], "ipd23.com") !== false) {
    DB::$dbName = 'cp4996_carrental';
    DB::$user = 'cp4996_carrental';
    DB::$password = 'WhuaZH1umzsvstZ5';
} else {
    DB::$dbName = 'carrental';
    DB::$user = 'carrental';
    DB::$password = 'WhuaZH1umzsvstZ5';
    DB::$host = 'localhost';
    DB::$port = 3333;
}

DB::$error_handler = 'db_error_handler'; // runs on mysql query errors
DB::$nonsql_error_handler = 'db_error_handler'; // runs on library errors (bad syntax, etc)

function db_error_handler($params)
{
    /*
    echo "<pre>\n";
    print_r($params);
    echo "<pre>\n";
    */
    global $log, $container;
    // log first
    $log->error("Database error: " . $params['error']);
    if (isset($params['query'])) {
        $log->error("SQL query: " . $params['query']);
    }
    http_response_code(500); // internal server error
    $twig = $container['view']->getEnvironment();
    die($twig->render('error_internal.html.twig'));
}

use Slim\Http\UploadedFile;

// Create and configure Slim app
$config = ['settings' => [
    'addContentLengthHeader' => false,
    'displayErrorDetails' => true
]];
$app = new \Slim\App($config);

// Fetch DI Container
$container = $app->getContainer();

//File pload directory
$container = $app->getContainer();
$container['upload_directory'] = __DIR__ . '/uploads';

// Register Twig View helper
$container['view'] = function ($c) {
    $view = new \Slim\Views\Twig(dirname(__FILE__) . '/templates', [
        'cache' => dirname(__FILE__) . '/tmplcache',
        'debug' => true, // This line should enable debug mode
    ]);
    // This value will be set for all twig templates
    $view->getEnvironment()->addGlobal('test1', 'VALUE');
    // Instantiate and add Slim specific extension
    $router = $c->get('router');
    $uri = \Slim\Http\Uri::createFromEnvironment(new \Slim\Http\Environment($_SERVER));
    $view->addExtension(new \Slim\Views\TwigExtension($router, $uri));
    return $view;
};

//Override the default Not Found Handler before creating App
$container['notFoundHandler'] = function ($container) {
    return function ($request, $response) use ($container) {
        $response = $response->withStatus(404);
        return $container['view']->render($response, '404.html.twig');
    };
};

// Flash messages handling

$container['view']->getEnvironment()->addGlobal('flashMessage', getAndClearFlashMessage());

function setFlashMessage($message)
{
    $_SESSION['flashMessage'] = $message;
}

// returns empty string if no message, otherwise returns string with message and clears is
function getAndClearFlashMessage()
{
    if (isset($_SESSION['flashMessage'])) {
        $message = $_SESSION['flashMessage'];
        unset($_SESSION['flashMessage']);
        return $message;
    }
    return "";
}


$container['view']->getEnvironment()->addGlobal('userSession', $_SESSION['user'] ??  null);
$passwordPepper = 'mmyb7oSAeXG9DTz2uFqu';
