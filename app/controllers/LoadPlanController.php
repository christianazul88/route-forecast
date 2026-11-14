<?php
declare(strict_types=1);

final class LoadPlanController
{
    public static function index(): void
    {
        require_login();
        $trucks = Truck::active();
        $boxes = BoxType::active();
        $selectedTruck = filter_var($_GET['truck_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        render('load_planner/index', [
            'title' => 'Load planner',
            'trucks' => $trucks,
            'boxes' => $boxes,
            'selectedTruck' => $selectedTruck,
        ]);
    }
}
