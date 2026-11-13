<?php
declare(strict_types=1);

final class DashboardController
{
    public static function index(): void
    {
        require_login();
        $today = date('Y-m-d');
        $lastUpcomingDate = date('Y-m-d', strtotime('+7 days'));
        render('dashboard', [
            'title' => 'Dashboard',
            'today' => $today,
            'lastUpcomingDate' => $lastUpcomingDate,
            'summary' => Trip::dashboard($today, $lastUpcomingDate),
        ]);
    }
}
