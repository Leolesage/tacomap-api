<?php
declare(strict_types=1);

require __DIR__ . '/../app/Core/Env.php';

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../app/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use App\Core\Env;
use App\Core\Cors;
use App\Core\Database;
use App\Core\Request;
use App\Core\Router;
use App\Core\Response;
use App\Core\AuthMiddleware;
use App\Controllers\AuthController;
use App\Controllers\TacosPlaceController;
use App\Controllers\UploadController;
use App\Controllers\TileController;

Env::load(__DIR__ . '/../.env');

$config = require __DIR__ . '/../config/config.php';
Database::init($config['db']);

Cors::handle($config['cors']);

set_error_handler(function (int $severity, string $message, string $file, int $line): void {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function (Throwable $e): void {
    Response::json(['error' => 'server_error'], 500);
});

$request = Request::capture();
$router = new Router();

$authController = new AuthController($config);
$tacosPlaceController = new TacosPlaceController($config);
$uploadController = new UploadController($config);
$tileController = new TileController();
$authMiddleware = new AuthMiddleware($config['jwt']['secret']);

$router->add('POST', '/api/auth/login', [$authController, 'login']);

$protected = [[$authMiddleware, 'handle']];
$router->add('GET', '/api/tacos-places', [$tacosPlaceController, 'index']);
$router->add('GET', '/api/tacos-places/{id}', [$tacosPlaceController, 'show']);
$router->add('POST', '/api/tacos-places', [$tacosPlaceController, 'store'], $protected);
$router->add('PUT', '/api/tacos-places/{id}', [$tacosPlaceController, 'update'], $protected);
$router->add('DELETE', '/api/tacos-places/{id}', [$tacosPlaceController, 'destroy'], $protected);
$router->add('GET', '/api/tacos-places/{id}/pdf', [$tacosPlaceController, 'pdf'], $protected);
$router->add('POST', '/api/uploads', [$uploadController, 'upload'], $protected);
$router->add('DELETE', '/api/uploads/{name}', [$uploadController, 'delete'], $protected);

$router->add('GET', '/tiles/{z}/{x}/{y}', [$tileController, 'show']);

$router->dispatch($request);
