<?php
declare(strict_types=1);

final class FuelController
{
    public static function index(): void
    {
        require_login();
        $tripId = isset($_GET['trip_id']) ? positive_id($_GET['trip_id']) : null;
        $detail = $tripId === null ? null : Trip::findDetail($tripId);
        if ($tripId !== null && $detail === null) {
            http_response_code(404);
            throw new InvalidArgumentException('Trip not found.');
        }
        $trip = $detail['trip'] ?? null;
        $truck = $trip ? Truck::find((int) $trip['truck_id']) : null;
        $warehouse = null;
        foreach (Branch::active() as $branch) {
            if ($branch['is_origin']) {
                $warehouse = $branch;
                break;
            }
        }
        $canSave = $trip && in_array($trip['status'], ['Planned', 'Dispatched'], true);
        $latest = FuelEstimate::latestPrice();
        $saved = $trip ? FuelEstimate::find($tripId) : null;
        $values = $saved['values'] ?? [
            'outbound_km' => (string) ($trip['total_distance_km'] ?? ''),
            'outbound_efficiency' => (string) ($truck['diesel_km_per_liter'] ?? ''),
            'return_efficiency' => '',
            'mode' => '',
            'method' => $trip ? 'ors' : 'manual',
            'manual_km' => '',
            'price' => (string) ($latest['php_per_liter'] ?? ''),
            'price_date' => (string) ($latest['observed_on'] ?? ''),
            'station' => (string) ($latest['station'] ?? ''),
        ];
        $result = $saved;
        $isSaved = $saved !== null;
        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            foreach ($values as $field => $previous) {
                $values[$field] = self::text($_POST, $field);
            }
            $result = null;
            $isSaved = false;
            try {
                $result = self::makeEstimate($values, $detail, $warehouse);
                if (self::text($_POST, 'action') === 'save') {
                    if (!$canSave) throw new InvalidArgumentException('Choose an editable trip before saving.');
                    FuelEstimate::save($tripId, $result, (int) current_user()['id']);
                    flash('success', 'Fuel estimate saved to the trip.');
                    redirect('fuel', ['trip_id' => $tripId]);
                }
            } catch (InvalidArgumentException | RoutingException $failure) {
                $error = $failure->getMessage();
            }
        }
        $trips = $trip ? [] : db()->query(
            'SELECT t.id, t.trip_no, t.trip_date, t.status, destination.name AS destination_name
             FROM trips t JOIN branches destination ON destination.id = t.destination_branch_id
             ORDER BY t.trip_date DESC, t.id DESC LIMIT 40'
        )->fetchAll();
        render('fuel/index', compact('trip', 'truck', 'warehouse', 'canSave', 'latest', 'values', 'result', 'isSaved', 'error', 'trips') + [
            'title' => 'Fuel planner', 'today' => date('Y-m-d'),
        ]);
    }

    public static function savePrice(): void
    {
        require_admin();
        require_post();
        verify_csrf();
        try {
            $price = self::number(self::text($_POST, 'price'), 'diesel price', .01, 1000);
            $day = self::day(self::text($_POST, 'price_date'));
            $station = self::station(self::text($_POST, 'station'));
            FuelEstimate::recordPrice($price, $day, $station, (int) current_user()['id']);
            flash('success', 'Team diesel price recorded.');
        } catch (InvalidArgumentException $error) {
            flash('warning', $error->getMessage());
        }
        redirect('fuel');
    }

    private static function makeEstimate(array $values, ?array $detail, ?array $warehouse): array
    {
        $trip = $detail['trip'] ?? null;
        if ($trip) $values['outbound_km'] = (string) $trip['total_distance_km'];
        $outbound = self::number($values['outbound_km'], 'outbound distance', .01, 100000);
        $efficiency = self::number($values['outbound_efficiency'], 'outbound diesel efficiency', .1, 100);
        $price = self::number($values['price'], 'diesel price', .01, 1000);
        self::day($values['price_date']);
        $values['station'] = self::station($values['station']);
        if (!in_array($values['mode'], ['one_way', 'warehouse'], true)) {
            throw new InvalidArgumentException('Choose whether the truck returns to the warehouse.');
        }
        $back = null;
        $returnEfficiency = null;
        $returnMinutes = null;
        $source = 'none';
        if ($values['mode'] === 'warehouse') {
            if ($values['return_efficiency'] !== '') {
                $returnEfficiency = self::number($values['return_efficiency'], 'return diesel efficiency', .1, 100);
            }
            if ($values['method'] === 'manual' && (!$trip || $warehouse)) {
                $back = self::number($values['manual_km'], 'return distance', 0, 100000);
                $source = 'manual';
            } elseif ($values['method'] === 'ors' && $trip) {
                if (!$warehouse) throw new InvalidArgumentException('Set an active warehouse origin in Branches to plan the return.');
                $lastStop = $detail['stops'][count($detail['stops']) - 1] ?? null;
                if (!$lastStop) throw new InvalidArgumentException('The trip is missing its final delivery stop.');
                $source = 'ors';
                if ((int) $lastStop['branch_id'] === (int) $warehouse['id']) {
                    $back = 0.0;
                    $returnMinutes = 0;
                } else {
                    try {
                        $route = RouteService::route([
                            [(float) $lastStop['lng'], (float) $lastStop['lat']],
                            [(float) $warehouse['lng'], (float) $warehouse['lat']],
                        ]);
                    } catch (RoutingException $error) {
                        throw new InvalidArgumentException($error->getMessage() . ' You can enter a measured return distance instead.');
                    }
                    $back = $route['distance_m'] / 1000;
                    $returnMinutes = (int) round($route['duration_s'] / 60);
                }
            } else {
                throw new InvalidArgumentException($trip && !$warehouse
                    ? 'Set an active warehouse origin in Branches to plan the return.'
                    : 'Choose a valid source for the return distance.');
            }
        }
        return FuelEstimate::calculate($outbound, $back, $efficiency, $returnEfficiency, $price) + [
            'values' => $values, 'mode' => $values['mode'], 'return_source' => $source,
            'return_destination' => $back === null ? null : ($warehouse['name'] ?? 'Warehouse'),
            'return_minutes' => $returnMinutes,
            'outbound_minutes' => $trip ? (int) round((float) $trip['total_duration_min']) : null,
            'destination_id' => $trip ? (int) $trip['destination_branch_id'] : null,
            'truck_id' => $trip ? (int) $trip['truck_id'] : null,
        ];
    }

    private static function text(array $input, string $key): string
    {
        return is_string($input[$key] ?? null) ? trim($input[$key]) : '';
    }

    private static function number(string $value, string $label, float $min, float $max): float
    {
        $amount = (float) $value;
        if (!is_numeric($value) || !is_finite($amount) || $amount < $min || $amount > $max) {
            throw new InvalidArgumentException('Enter a valid ' . $label . '.');
        }
        return round($amount, 2);
    }

    private static function day(string $input): string
    {
        $day = preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $input)
            ? DateTimeImmutable::createFromFormat('!Y-m-d', $input) : false;
        if ($day === false || $day->format('Y-m-d') !== $input || $input > date('Y-m-d')) {
            throw new InvalidArgumentException('Enter the date when the diesel price was observed.');
        }
        return $input;
    }

    private static function station(string $input): string
    {
        if ($input === '' || mb_strlen($input) > 150) {
            throw new InvalidArgumentException('Enter a station or price source and its location.');
        }
        return $input;
    }
}
