<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

$routes = [
    'login' => [AuthController::class, 'login'],
    'logout' => [AuthController::class, 'logout'],
    'branches' => [BranchController::class, 'index'],
    'branch_map' => [BranchController::class, 'map'],
    'branch_form' => [BranchController::class, 'form'],
    'branch_save' => [BranchController::class, 'save'],
    'branch_archive' => [BranchController::class, 'archive'],
    'trucks' => [TruckController::class, 'index'],
    'truck_form' => [TruckController::class, 'form'],
    'truck_save' => [TruckController::class, 'save'],
    'truck_archive' => [TruckController::class, 'archive'],
    'boxes' => [BoxTypeController::class, 'index'],
    'box_form' => [BoxTypeController::class, 'form'],
    'box_save' => [BoxTypeController::class, 'save'],
    'box_archive' => [BoxTypeController::class, 'archive'],
    'load_planner' => [LoadPlanController::class, 'index'],
    'load_plan_save' => [LoadPlanController::class, 'save'],
    'fuel' => [FuelController::class, 'index'],
    'diesel_price_save' => [FuelController::class, 'savePrice'],
    'forecast' => [RouteForecastController::class, 'index'],
    'route_proxy' => [RouteProxyController::class, 'route'],
    'dashboard' => [DashboardController::class, 'index'],
    'trips' => [TripController::class, 'index'],
    'trip_save' => [TripController::class, 'save'],
    'trip_detail' => [TripController::class, 'detail'],
    'trip_status' => [TripController::class, 'changeStatus'],
    'trip_sheet' => [TripController::class, 'sheet'],
    'trip_csv' => [TripController::class, 'csv'],
];

$page = (string) ($_GET['page'] ?? 'dashboard');
if (!isset($routes[$page])) {
    http_response_code(404);
    exit('Page not found.');
}

try {
    $routes[$page]();
} catch (Throwable $error) {
    $status = http_response_code();
    if ($status < 400) {
        http_response_code(500);
    }
    error_log((string) $error);
    $message = $error instanceof PDOException ? 'A database error occurred. Check the server log.' : $error->getMessage();
    render('error', ['title' => 'Something went wrong', 'message' => $message]);
}
