<?php
declare(strict_types=1);

final class TripController
{
    public static function index(): void
    {
        require_login();
        $filters = [
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'truck_id' => null,
        ];
        foreach (['date_from', 'date_to'] as $key) {
            if ($filters[$key] !== '' && !self::validDate($filters[$key])) {
                http_response_code(422);
                throw new InvalidArgumentException('Enter a valid filter date.');
            }
        }
        if ($filters['status'] !== '' && !in_array($filters['status'], Trip::STATUSES, true)) {
            http_response_code(422);
            throw new InvalidArgumentException('Choose a valid status filter.');
        }
        if (!empty($_GET['truck_id'])) {
            $filters['truck_id'] = positive_id($_GET['truck_id']);
        }
        $pageNumber = isset($_GET['p']) ? positive_id($_GET['p']) : 1;
        render('trips/index', [
            'title' => 'Trips',
            'filters' => $filters,
            'trucks' => Truck::all(),
            'listing' => Trip::listing($filters, $pageNumber),
        ]);
    }

    public static function save(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        try {
            $user = current_user();
            if (!$user) {
                self::json(['error' => 'Your session expired. Sign in again.'], 401);
                return;
            }
            require_post();
            verify_csrf();
            $raw = file_get_contents('php://input');
            if ($raw === false || strlen($raw) > 4096) {
                throw new InvalidArgumentException('Invalid trip request.');
            }
            $input = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($input)) {
                throw new InvalidArgumentException('Invalid trip details.');
            }
            $originId = self::jsonId($input['origin_id'] ?? null);
            $destinationId = self::jsonId($input['destination_id'] ?? null);
            $truckId = self::jsonId($input['truck_id'] ?? null);
            $stopIds = $input['stop_ids'] ?? null;
            if (!is_array($stopIds) || array_values($stopIds) !== $stopIds || count($stopIds) > 10) {
                throw new InvalidArgumentException('Choose up to 10 optional stops.');
            }
            $allIds = [$originId, ...$stopIds, $destinationId];
            foreach ($stopIds as $id) {
                self::jsonId($id);
            }
            if (count(array_unique($allIds)) !== count($allIds)) {
                throw new InvalidArgumentException('Route branches must be different locations.');
            }
            $branches = Branch::activeByIds($allIds);
            if (count($branches) !== count($allIds)) {
                throw new InvalidArgumentException('One or more route branches are inactive or missing. Refresh the forecast.');
            }
            $truck = Truck::find($truckId);
            if (!$truck || !$truck['is_active']) {
                throw new InvalidArgumentException('Choose an active truck.');
            }
            $driverName = trim((string) ($input['driver_name'] ?? ''));
            if ($driverName === '' || mb_strlen($driverName) > 120) {
                throw new InvalidArgumentException('Enter a driver name of at most 120 characters.');
            }
            $tripDate = (string) ($input['trip_date'] ?? '');
            if (!self::validDate($tripDate)) {
                throw new InvalidArgumentException('Choose a valid trip date.');
            }
            $corridor = $input['corridor_km'] ?? null;
            if (!is_numeric($corridor) || (float) $corridor < 0.5 || (float) $corridor > 5) {
                throw new InvalidArgumentException('Corridor must be between 0.5 and 5 km.');
            }
            $coordinates = static fn(int $id): array => [(float) $branches[$id]['lng'], (float) $branches[$id]['lat']];
            $direct = RouteService::route([$coordinates($originId), $coordinates($destinationId)]);
            $finalCoordinates = array_map($coordinates, $allIds);
            $finalRoute = $stopIds ? RouteService::route($finalCoordinates) : $direct;
            $detours = [];
            foreach ($stopIds as $id) {
                $candidateRoute = RouteService::route([$coordinates($originId), $coordinates($id), $coordinates($destinationId)]);
                $detours[$id] = [
                    'km' => max(0, ($candidateRoute['distance_m'] - $direct['distance_m']) / 1000),
                    'min' => max(0, ($candidateRoute['duration_s'] - $direct['duration_s']) / 60),
                ];
            }
            $tripId = Trip::create([
                'origin_id' => $originId,
                'destination_id' => $destinationId,
                'stop_ids' => $stopIds,
                'truck_id' => $truckId,
                'driver_name' => $driverName,
                'trip_date' => $tripDate,
                'corridor_km' => round((float) $corridor, 2),
                'created_by' => $user['id'],
            ], $finalRoute, $detours);
            self::json(['trip_id' => $tripId, 'url' => url('trip_detail', ['id' => $tripId])], 201);
        } catch (RoutingException $error) {
            self::json(['error' => $error->getMessage()], $error->httpStatus);
        } catch (InvalidArgumentException | JsonException $error) {
            self::json(['error' => $error->getMessage()], 422);
        } catch (RuntimeException $error) {
            $status = http_response_code();
            self::json(['error' => $error->getMessage()], $status >= 400 ? $status : 400);
        } catch (Throwable $error) {
            error_log((string) $error);
            self::json(['error' => 'Could not save the trip. Please try again.'], 500);
        }
    }

    public static function detail(): void
    {
        require_login();
        $detail = self::loadDetail();
        render('trips/detail', ['title' => $detail['trip']['trip_no'], 'detail' => $detail, 'mapAssets' => true]);
    }

    public static function sheet(): void
    {
        require_login();
        $detail = self::loadDetail();
        render('trips/sheet', ['title' => 'Trip sheet ' . $detail['trip']['trip_no'], 'detail' => $detail]);
    }

    public static function csv(): void
    {
        require_login();
        $detail = self::loadDetail();
        $trip = $detail['trip'];
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $trip['trip_no'] . '.csv"');
        header('Cache-Control: no-store');
        $output = fopen('php://output', 'wb');
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['Route Forecast trip sheet', $trip['trip_no']]);
        fputcsv($output, ['Trip date', $trip['trip_date']]);
        fputcsv($output, ['Status', $trip['status']]);
        fputcsv($output, ['Truck', csv_safe($trip['plate_no'] . ' · ' . $trip['truck_name'])]);
        fputcsv($output, ['Driver', csv_safe($trip['driver_name'])]);
        fputcsv($output, ['Total distance (km)', $trip['total_distance_km']]);
        fputcsv($output, ['Total duration (min)', $trip['total_duration_min']]);
        fputcsv($output, []);
        fputcsv($output, ['Order', 'Stop type', 'Code', 'Branch', 'Address', 'Contact person', 'Contact number', 'Leg distance (km)', 'Leg duration (min)', 'Extra detour (km)', 'Extra detour (min)', 'Notes']);
        fputcsv($output, [0, 'Origin', csv_safe($trip['origin_code']), csv_safe($trip['origin_name']), csv_safe($trip['origin_address']), csv_safe($trip['origin_contact_person']), csv_safe($trip['origin_contact_no']), 0, 0, '', '', '']);
        foreach ($detail['stops'] as $stop) {
            fputcsv($output, [
                $stop['stop_order'],
                $stop['is_main_destination'] ? 'Main destination' : 'Optional stop',
                csv_safe($stop['code']),
                csv_safe($stop['name']),
                csv_safe($stop['address']),
                csv_safe($stop['contact_person']),
                csv_safe($stop['contact_no']),
                $stop['leg_distance_km'] ?? '',
                $stop['leg_duration_min'] ?? '',
                $stop['is_main_destination'] ? '' : $stop['detour_km'],
                $stop['is_main_destination'] ? '' : $stop['detour_min'],
                csv_safe($stop['notes']),
            ]);
        }
        fclose($output);
    }

    public static function changeStatus(): void
    {
        require_login();
        require_post();
        verify_csrf();
        $id = positive_id($_POST['id'] ?? null);
        $newStatus = (string) ($_POST['status'] ?? '');
        try {
            Trip::changeStatus($id, $newStatus);
            flash('success', 'Trip status changed to ' . $newStatus . '.');
        } catch (InvalidArgumentException $error) {
            AuditLog::tryRecord(current_user()['id'], 'change_rejected', 'trip', $id, ['page' => 'trip_status']);
            flash('warning', $error->getMessage());
        }
        redirect('trip_detail', ['id' => $id]);
    }

    private static function loadDetail(): array
    {
        $id = positive_id($_GET['id'] ?? null);
        $detail = Trip::findDetail($id);
        if (!$detail) {
            http_response_code(404);
            throw new RuntimeException('Trip not found.');
        }
        return $detail;
    }

    private static function validDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private static function jsonId(mixed $value): int
    {
        if (!is_int($value) || $value < 1) {
            throw new InvalidArgumentException('Choose valid branch and truck records.');
        }
        return $value;
    }

    private static function json(array $body, int $status): void
    {
        http_response_code($status);
        echo json_encode($body, JSON_THROW_ON_ERROR);
    }
}
