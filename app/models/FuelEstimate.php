<?php
declare(strict_types=1);

final class FuelEstimate
{
    public static function calculate(
        float $outboundKm, ?float $returnKm, float $outboundEfficiency, ?float $returnEfficiency, float $price
    ): array {
        if (!is_finite($outboundKm) || $outboundKm <= 0 || !is_finite($outboundEfficiency) || $outboundEfficiency <= 0
            || !is_finite($price) || $price <= 0 || ($returnKm !== null && (!is_finite($returnKm) || $returnKm < 0))
            || ($returnEfficiency !== null && (!is_finite($returnEfficiency) || $returnEfficiency <= 0))) {
            throw new InvalidArgumentException('Enter valid distances, diesel efficiency and price.');
        }
        $outboundLiters = $outboundKm / $outboundEfficiency;
        $returnLiters = ($returnKm ?? 0) / ($returnEfficiency ?? $outboundEfficiency);
        $totalLiters = $outboundLiters + $returnLiters;
        return [
            'outbound_km' => $outboundKm, 'return_km' => $returnKm, 'total_km' => $outboundKm + ($returnKm ?? 0),
            'outbound_liters' => $outboundLiters, 'return_liters' => $returnKm === null ? null : $returnLiters,
            'total_liters' => $totalLiters, 'total_cost_php' => round($totalLiters * $price, 2),
        ];
    }

    public static function latestPrice(): ?array
    {
        $query = db()->prepare('SELECT php_per_liter, observed_on, station FROM diesel_prices
            WHERE observed_on <= ? ORDER BY observed_on DESC, id DESC LIMIT 1');
        $query->execute([date('Y-m-d')]);
        return $query->fetch() ?: null;
    }

    public static function recordPrice(float $price, string $day, string $station, int $userId): void
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $insert = $pdo->prepare('INSERT INTO diesel_prices (php_per_liter, observed_on, station, recorded_by) VALUES (?, ?, ?, ?)');
            $insert->execute([$price, $day, $station, $userId]);
            audit('record', 'diesel_price', (int) $pdo->lastInsertId(), ['observed_on' => $day]);
            $pdo->commit();
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }

    public static function find(int $tripId): ?array
    {
        $query = db()->prepare('SELECT estimate_json FROM trip_fuel_estimates WHERE trip_id = ?');
        $query->execute([$tripId]);
        $payload = $query->fetchColumn();
        return $payload === false ? null : json_decode($payload, true, 32, JSON_THROW_ON_ERROR);
    }

    public static function save(int $tripId, array $estimate, int $userId): void
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare('SELECT status, destination_branch_id, truck_id, total_distance_km FROM trips WHERE id = ? FOR UPDATE');
            $lock->execute([$tripId]);
            $trip = $lock->fetch();
            if (!$trip || !in_array($trip['status'], ['Planned', 'Dispatched'], true)) {
                throw new InvalidArgumentException('Only planned or dispatched trips can have an updated fuel estimate.');
            }
            if ((int) $trip['destination_branch_id'] !== $estimate['destination_id']
                || (int) $trip['truck_id'] !== $estimate['truck_id']
                || (float) $trip['total_distance_km'] !== $estimate['outbound_km']) {
                throw new InvalidArgumentException('The trip changed. Reopen it before saving an estimate.');
            }
            $upsert = $pdo->prepare('INSERT INTO trip_fuel_estimates (trip_id, estimate_json, updated_by) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE estimate_json = VALUES(estimate_json), updated_by = VALUES(updated_by)');
            $upsert->execute([$tripId, json_encode($estimate, JSON_THROW_ON_ERROR), $userId]);
            audit('save', 'trip_fuel_estimate', $tripId, ['mode' => $estimate['mode']]);
            $pdo->commit();
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }
}
