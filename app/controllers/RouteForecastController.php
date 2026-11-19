<?php
declare(strict_types=1);

final class RouteForecastController
{
    public static function index(): void
    {
        require_login();
        $branches = Branch::active();
        $trucks = Truck::active();
        $routeBranches = array_values(array_filter($branches, static fn(array $branch): bool => !str_starts_with($branch['code'], 'DEMO-')));
        $routeTrucks = array_values(array_filter($trucks, static fn(array $truck): bool => !str_starts_with($truck['plate_no'], 'DEMO-')));
        $recommended = Setting::number('recommended_max_min', 5);
        $marginal = Setting::number('marginal_max_min', 15);
        if ($recommended <= 0 || $marginal < $recommended) {
            $recommended = 5;
            $marginal = 15;
        }
        render('forecast/index', [
            'title' => 'Route Forecast',
            'branches' => $routeBranches,
            'trucks' => $routeTrucks,
            'today' => date('Y-m-d'),
            'recommendedMax' => $recommended,
            'marginalMax' => $marginal,
            'mapAssets' => true,
        ]);
    }
}
