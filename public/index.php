<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'WarehouseStock\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

use WarehouseStock\Controllers\AuthController;
use WarehouseStock\Controllers\DepartmentController;
use WarehouseStock\Controllers\ItemController;
use WarehouseStock\Controllers\ReportController;
use WarehouseStock\Controllers\StockController;
use WarehouseStock\Database\Connection;
use WarehouseStock\Helpers\Env;
use WarehouseStock\Helpers\ResponseHelper;
use WarehouseStock\Middleware\AuthMiddleware;

Env::load(dirname(__DIR__));

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = rtrim($uri ?: '/', '/');
$path = $path === '' ? '/' : $path;

if ($method === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    http_response_code(204);
    exit;
}

header('Access-Control-Allow-Origin: *');

try {
    if (!AuthMiddleware::requireAuth($path)) {
        exit;
    }

    if ($method === 'GET' && $path === '/api/health') {
        ResponseHelper::success(['time' => date('Y-m-d H:i:s')], 'Warehouse API is running');
        exit;
    }

    $db = Connection::get();

    if ($method === 'POST' && $path === '/api/auth/login') {
        (new AuthController($db))->login();
        exit;
    }

    $departmentController = new DepartmentController($db);
    $itemController = new ItemController($db);
    $stockController = new StockController($db);
    $reportController = new ReportController($db);

    if ($method === 'GET' && $path === '/api/departments') {
        $departmentController->index();
        exit;
    }

    if ($method === 'POST' && $path === '/api/departments') {
        $departmentController->create();
        exit;
    }

    if ($method === 'PUT' && preg_match('#^/api/departments/(\d+)$#', $path, $matches)) {
        $departmentController->update((int) $matches[1]);
        exit;
    }

    if ($method === 'DELETE' && preg_match('#^/api/departments/(\d+)$#', $path, $matches)) {
        $departmentController->delete((int) $matches[1]);
        exit;
    }

    if ($method === 'GET' && $path === '/api/items') {
        $itemController->index();
        exit;
    }

    if ($method === 'POST' && $path === '/api/items') {
        $itemController->create();
        exit;
    }

    if ($method === 'PUT' && preg_match('#^/api/items/(\d+)$#', $path, $matches)) {
        $itemController->update((int) $matches[1]);
        exit;
    }

    if ($method === 'DELETE' && preg_match('#^/api/items/(\d+)$#', $path, $matches)) {
        $itemController->delete((int) $matches[1]);
        exit;
    }

    if ($method === 'POST' && $path === '/api/stock/in') {
        $stockController->stockIn();
        exit;
    }

    if ($method === 'POST' && $path === '/api/stock/out') {
        $stockController->stockOut();
        exit;
    }

    if ($method === 'GET' && $path === '/api/stock/movements') {
        $stockController->movements();
        exit;
    }

    if ($method === 'GET' && $path === '/api/reports/current-stock') {
        $reportController->currentStock();
        exit;
    }

    if ($method === 'GET' && $path === '/api/reports/stock-movement') {
        $reportController->stockMovement();
        exit;
    }

    if ($method === 'GET' && $path === '/api/reports/stock-out-by-department') {
        $reportController->stockOutByDepartment();
        exit;
    }

    if ($method === 'GET' && $path === '/api/activity-logs') {
        $reportController->activityLogs();
        exit;
    }

    ResponseHelper::error('Endpoint not found', 404);
} catch (RuntimeException $exception) {
    error_log($exception->getMessage());
    ResponseHelper::error($exception->getMessage(), 500);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    ResponseHelper::error('Internal server error', 500);
}
