<?php
declare(strict_types=1);

final class RouteForecastController
{
    public static function index(): void
    {
        require_login();
        $branches = Branch::active();
        $recommended = Setting::number('recommended_max_min', 5);
        $marginal = Setting::number('marginal_max_min', 15);
        if ($recommended <= 0 || $marginal < $recommended) {
            $recommended = 5;
            $marginal = 15;
        }
        render('forecast/index', [
            'title' => 'Route Forecast',
            'branches' => $branches,
            'trucks' => Truck::active(),
            'today' => date('Y-m-d'),
            'recommendedMax' => $recommended,
            'marginalMax' => $marginal,
            'mapAssets' => true,
        ]);
    }
}
