<?php
declare(strict_types=1);

final class Trip
{
    public const STATUSES = ['Planned', 'Dispatched', 'Completed', 'Cancelled'];

    public static function create(array $data, array $route, array $detours): int
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $tripNo = 'RF-' . str_replace('-', '', $data['trip_date']) . '-' . strtoupper(bin2hex(random_bytes(4)));
            $statement = $pdo->prepare(
                'INSERT INTO trips (trip_no, origin_branch_id, destination_branch_id, truck_id, driver_name, trip_date, corridor_km, total_distance_km, total_duration_min, route_geometry, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([
                $tripNo,
                $data['origin_id'],
                $data['destination_id'],
                $data['truck_id'],
                $data['driver_name'],
                $data['trip_date'],
                $data['corridor_km'],
                round($route['distance_m'] / 1000, 2),
                round($route['duration_s'] / 60, 2),
                $route['geometry'],
                'Planned',
                $data['created_by'],
            ]);
            $tripId = (int) $pdo->lastInsertId();
            $orderedStops = [...$data['stop_ids'], $data['destination_id']];
            $legs = count($route['legs']) === count($orderedStops) ? $route['legs'] : [];
            $stopStatement = $pdo->prepare(
                'INSERT INTO trip_stops (trip_id, branch_id, stop_order, is_main_destination, detour_km, detour_min, leg_distance_km, leg_duration_min, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL)'
            );
            foreach ($orderedStops as $index => $branchId) {
                $isMain = $branchId === $data['destination_id'];
                $detour = $detours[$branchId] ?? ['km' => 0, 'min' => 0];
                $leg = $legs[$index] ?? null;
                $stopStatement->execute([
                    $tripId,
                    $branchId,
                    $index + 1,
                    $isMain ? 1 : 0,
                    round($detour['km'], 2),
                    round($detour['min'], 2),
                    $leg ? round($leg['distance_m'] / 1000, 2) : null,
                    $leg ? round($leg['duration_s'] / 60, 2) : null,
                ]);
            }
            audit('create', 'trip', $tripId, ['trip_no' => $tripNo, 'optional_stops' => count($data['stop_ids'])]);
            $pdo->commit();
            return $tripId;
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }

    public static function listing(array $filters, int $pageNumber): array
    {
        $where = [];
        $params = [];
        if ($filters['date_from'] !== '') {
            $where[] = 't.trip_date >= ?';
            $params[] = $filters['date_from'];
        }
        if ($filters['date_to'] !== '') {
            $where[] = 't.trip_date <= ?';
            $params[] = $filters['date_to'];
        }
        if ($filters['status'] !== '') {
            $where[] = 't.status = ?';
            $params[] = $filters['status'];
        }
        if ($filters['truck_id'] !== null) {
            $where[] = 't.truck_id = ?';
            $params[] = $filters['truck_id'];
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $countStatement = db()->prepare('SELECT COUNT(*) FROM trips t' . $whereSql);
        $countStatement->execute($params);
        $total = (int) $countStatement->fetchColumn();
        $pageSize = 25;
        $pages = max(1, (int) ceil($total / $pageSize));
        $pageNumber = min($pageNumber, $pages);

        $sql = 'SELECT t.id, t.trip_no, t.trip_date, t.status, t.driver_name, t.total_distance_km, t.total_duration_min,
                       origin.name AS origin_name, destination.name AS destination_name, truck.plate_no,
                       (SELECT COUNT(*) - 1 FROM trip_stops s WHERE s.trip_id = t.id) AS extra_stops
                FROM trips t
                JOIN branches origin ON origin.id = t.origin_branch_id
                JOIN branches destination ON destination.id = t.destination_branch_id
                JOIN trucks truck ON truck.id = t.truck_id' . $whereSql .
                ' ORDER BY t.trip_date DESC, t.id DESC LIMIT ? OFFSET ?';
        $statement = db()->prepare($sql);
        foreach ($params as $index => $value) {
            $statement->bindValue($index + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->bindValue(count($params) + 1, $pageSize, PDO::PARAM_INT);
        $statement->bindValue(count($params) + 2, ($pageNumber - 1) * $pageSize, PDO::PARAM_INT);
        $statement->execute();
        return ['rows' => $statement->fetchAll(), 'total' => $total, 'page' => $pageNumber, 'pages' => $pages];
    }

    public static function findDetail(int $id): ?array
    {
        $statement = db()->prepare(
            'SELECT t.*, origin.code AS origin_code, origin.name AS origin_name, origin.address AS origin_address,
                    origin.lat AS origin_lat, origin.lng AS origin_lng, origin.contact_person AS origin_contact_person,
                    origin.contact_no AS origin_contact_no,
                    destination.code AS destination_code, destination.name AS destination_name,
                    truck.plate_no, truck.name AS truck_name, truck.capacity_kg, creator.full_name AS created_by_name
             FROM trips t
             JOIN branches origin ON origin.id = t.origin_branch_id
             JOIN branches destination ON destination.id = t.destination_branch_id
             JOIN trucks truck ON truck.id = t.truck_id
             JOIN users creator ON creator.id = t.created_by
             WHERE t.id = ?'
        );
        $statement->execute([$id]);
        $trip = $statement->fetch();
        if (!$trip) {
            return null;
        }
        $stopStatement = db()->prepare(
            'SELECT s.*, b.code, b.name, b.address, b.lat, b.lng, b.contact_person, b.contact_no
             FROM trip_stops s JOIN branches b ON b.id = s.branch_id
             WHERE s.trip_id = ? ORDER BY s.stop_order'
        );
        $stopStatement->execute([$id]);
        return ['trip' => $trip, 'stops' => $stopStatement->fetchAll()];
    }

    public static function dashboard(string $today, string $lastUpcomingDate): array
    {
        $counts = array_fill_keys(self::STATUSES, 0);
        foreach (db()->query('SELECT status, COUNT(*) AS total FROM trips GROUP BY status')->fetchAll() as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }
        $baseSql = 'SELECT t.id, t.trip_no, t.trip_date, t.status, t.total_distance_km,
                           origin.name AS origin_name, destination.name AS destination_name, truck.plate_no
                    FROM trips t
                    JOIN branches origin ON origin.id = t.origin_branch_id
                    JOIN branches destination ON destination.id = t.destination_branch_id
                    JOIN trucks truck ON truck.id = t.truck_id';
        $todayStatement = db()->prepare($baseSql . ' WHERE t.trip_date = ? ORDER BY t.id DESC LIMIT 10');
        $todayStatement->execute([$today]);
        $upcomingStatement = db()->prepare($baseSql . " WHERE t.trip_date > ? AND t.trip_date <= ? AND t.status IN ('Planned', 'Dispatched') ORDER BY t.trip_date, t.id LIMIT 10");
        $upcomingStatement->execute([$today, $lastUpcomingDate]);
        $todayCountStatement = db()->prepare('SELECT COUNT(*) FROM trips WHERE trip_date = ?');
        $todayCountStatement->execute([$today]);
        return [
            'counts' => $counts,
            'today_count' => (int) $todayCountStatement->fetchColumn(),
            'today' => $todayStatement->fetchAll(),
            'upcoming' => $upcomingStatement->fetchAll(),
        ];
    }

    public static function changeStatus(int $id, string $newStatus): void
    {
        $transitions = [
            'Planned' => ['Dispatched', 'Cancelled'],
            'Dispatched' => ['Completed', 'Cancelled'],
            'Completed' => [],
            'Cancelled' => [],
        ];
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare('SELECT status FROM trips WHERE id = ? FOR UPDATE');
            $statement->execute([$id]);
            $currentStatus = $statement->fetchColumn();
            if ($currentStatus === false) {
                throw new InvalidArgumentException('Trip not found.');
            }
            if (!in_array($newStatus, $transitions[$currentStatus] ?? [], true)) {
                throw new InvalidArgumentException('That status change is not allowed.');
            }
            $pdo->prepare('UPDATE trips SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
            audit('status_change', 'trip', $id, ['from' => $currentStatus, 'to' => $newStatus]);
            $pdo->commit();
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }
}
