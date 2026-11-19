<?php
declare(strict_types=1);

final class LoadPlan
{
    public const MAX_ITEMS = 1200;
    public const MAX_LINES = 40;

    public static function find(int $tripId): ?array
    {
        $query = db()->prepare('SELECT source_hash, revision, payload_json, updated_at FROM trip_load_plans WHERE trip_id = ?');
        $query->execute([$tripId]);
        $row = $query->fetch();
        if (!$row) return null;
        $row['payload'] = json_decode($row['payload_json'], true, 32, JSON_THROW_ON_ERROR);
        unset($row['payload_json']);
        return $row;
    }

    public static function context(array $detail, array $truck): array
    {
        $stops = array_map(static fn(array $stop): array => [
            'id' => (int) $stop['id'],
            'code' => $stop['code'],
            'name' => $stop['name'],
            'order' => (int) $stop['stop_order'],
        ], $detail['stops']);
        $profile = Truck::loadProfile($truck);
        return [
            'truck' => $profile, 'stops' => $stops,
            'hash' => hash('sha256', json_encode([$profile, $stops], JSON_THROW_ON_ERROR)),
        ];
    }

    public static function save(int $tripId, array $payload, int $userId): int
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare('SELECT status, truck_id FROM trips WHERE id = ? FOR UPDATE');
            $lock->execute([$tripId]);
            $trip = $lock->fetch();
            if (!$trip || $trip['status'] !== 'Planned') throw new InvalidArgumentException('Load plans can be saved only on planned trips.');
            $truckQuery = $pdo->prepare('SELECT * FROM trucks WHERE id = ? FOR UPDATE');
            $truckQuery->execute([$trip['truck_id']]);
            $truck = $truckQuery->fetch();
            $context = self::context(Trip::findDetail($tripId), $truck);
            if (!is_string($payload['sourceHash'] ?? null) || !hash_equals($context['hash'], $payload['sourceHash'])) {
                throw new InvalidArgumentException('The truck or delivery stops changed. Reload and recalculate the plan.');
            }
            if (!$context['truck']['confirmed'] || !$context['truck']['openings']) {
                throw new InvalidArgumentException('Confirm the truck openings before saving a trip load plan.');
            }
            $revisionQuery = $pdo->prepare('SELECT revision FROM trip_load_plans WHERE trip_id = ? FOR UPDATE');
            $revisionQuery->execute([$tripId]);
            $previous = (int) $revisionQuery->fetchColumn();
            if (($payload['revision'] ?? null) !== $previous) {
                throw new InvalidArgumentException('Someone saved another version. Reload before replacing it.');
            }
            $data = LoadPlanValidation::validate($payload, $context);
            $revision = $previous + 1;
            $query = $pdo->prepare(
                'INSERT INTO trip_load_plans (trip_id, source_hash, revision, payload_json, updated_by) VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE source_hash = VALUES(source_hash), revision = VALUES(revision),
                     payload_json = VALUES(payload_json), updated_by = VALUES(updated_by)'
            );
            $query->execute([$tripId, $context['hash'], $revision, json_encode($data, JSON_THROW_ON_ERROR), $userId]);
            audit('save', 'trip_load_plan', $tripId, ['revision' => $revision, 'units' => count($data['placements'])]);
            $pdo->commit();
            return $revision;
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }
}
