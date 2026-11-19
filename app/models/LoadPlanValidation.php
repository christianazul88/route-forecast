<?php
declare(strict_types=1);

final class LoadPlanValidation
{
    private const EPS = 0.00001;

    public static function validate(array $input, array $context): array
    {
        $truck = $context['truck'];
        $clearance = self::number($input['clearance'] ?? null, 0, 50);
        $bay = ['l' => $truck['length'] - 2 * $clearance, 'w' => $truck['width'] - 2 * $clearance, 'h' => $truck['height'] - $clearance];
        if (min($bay) <= 0) throw new InvalidArgumentException('The clearance leaves no usable cargo space.');
        $doors = [];
        foreach ($truck['openings'] as $id => $door) {
            $door['start'] = $door['offset'];
            $door['end'] = min($door['side'] === 'rear' ? $bay['w'] : $bay['l'], $door['offset'] + $door['width'] - 2 * $clearance);
            $door['top'] = min($bay['h'], $door['sill'] + $door['height'] - $clearance);
            $doors[$id] = $door;
        }
        $stops = [];
        foreach ($context['stops'] as $stop) {
            $ids = $input['stopDoors'][$stop['id']] ?? null;
            if (!is_array($ids) || count($ids) > count($doors)) self::invalid();
            foreach ($ids as $id) if (!is_int($id) || !isset($doors[$id])) self::invalid();
            $stops[$stop['id']] = array_values(array_unique($ids));
        }
        $manifest = $input['lines'] ?? null;
        $placements = $input['placements'] ?? null;
        if (!is_array($manifest) || !$manifest || count($manifest) > LoadPlan::MAX_LINES ||
            !is_array($placements) || !$placements || count($placements) > LoadPlan::MAX_ITEMS) self::invalid();
        $lines = self::lines($manifest, $stops);
        $counts = array_fill_keys(array_keys($lines), 0);
        $prior = [];
        $weight = 0.0;
        $lastStop = PHP_INT_MAX;
        $orders = array_column($context['stops'], 'order', 'id');
        foreach ($placements as $item) {
            if (!is_array($item) || !is_int($item['lineId'] ?? null) || !is_int($item['doorId'] ?? null)
                || !isset($lines[$item['lineId']], $doors[$item['doorId']])) self::invalid();
            $line = $lines[$item['lineId']];
            $box = $line['box'];
            $door = $doors[$item['doorId']];
            $order = $orders[$line['stopId']];
            if ($order > $lastStop || !in_array($item['doorId'], $stops[$line['stopId']], true)
                || ++$counts[$line['id']] > $line['quantity']) self::invalid();
            $lastStop = $order;
            $shape = [];
            foreach (['x' => 'l', 'y' => 'w', 'z' => 'h'] as $position => $dimension) {
                $shape[$position] = self::number($item[$position] ?? null, 0, $bay[$dimension]);
                $shape[$dimension] = self::number($item[$dimension] ?? null, .00001, $bay[$dimension]);
                if ($shape[$position] + $shape[$dimension] > $bay[$dimension] + self::EPS) self::invalid();
            }
            self::checkGeometry($shape, $box, $line, $door, $bay, $prior);
            $weight += $box['weight'];
            if ($weight > $truck['capacity'] + self::EPS) self::invalid();
            $prior[] = $shape + [
                'lineId' => $line['id'], 'boxId' => $box['id'], 'stopId' => $line['stopId'],
                'doorId' => $item['doorId'], 'canSupport' => $line['canSupport'], 'count' => 1,
            ];
        }
        $reasons = [];
        foreach ($lines as $id => $line) {
            $reason = $input['results'][$id] ?? '';
            if (is_string($reason) && mb_strlen($reason) <= 120) $reasons[$id] = $reason;
        }
        return ['clearance' => $clearance, 'stopDoors' => $stops, 'lines' => array_values($lines), 'placements' => $prior, 'results' => $reasons];
    }

    private static function lines(array $manifest, array $stops): array
    {
        $lines = [];
        foreach ($manifest as $entry) {
            if (!is_array($entry) || !is_int($entry['id'] ?? null) || $entry['id'] < 1 || isset($lines[$entry['id']])
                || !is_int($entry['stopId'] ?? null) || !isset($stops[$entry['stopId']]) || !is_int($entry['quantity'] ?? null)
                || $entry['quantity'] < 1 || $entry['quantity'] > 10000 || !is_array($entry['box'] ?? null)) self::invalid();
            $original = $entry['box'];
            $id = $original['id'] ?? null;
            $name = $original['name'] ?? null;
            $shape = $original['shape'] ?? '';
            if ((!is_int($id) && (!is_string($id) || !preg_match('/^custom-[0-9]+$/', $id)))
                || !is_string($name) || trim($name) === '' || mb_strlen($name) > 150 || !isset(BoxType::SHAPES[$shape])) self::invalid();
            $length = self::number($original['length'] ?? null, .00001, 2000);
            $width = $shape === 'Cylinder' ? $length : self::number($original['width'] ?? null, .00001, 2000);
            $box = compact('id', 'name', 'shape', 'length', 'width') + [
                'height' => self::number($original['height'] ?? null, .00001, 2000),
                'weight' => self::number($original['weight'] ?? null, .00001, 9999999.999),
                'canTilt' => $shape !== 'Cylinder' && ($original['canTilt'] ?? false) === true,
            ];
            $lines[$entry['id']] = [
                'id' => $entry['id'], 'stopId' => $entry['stopId'], 'quantity' => $entry['quantity'], 'box' => $box,
                'floorOnly' => $shape !== 'Box' || ($entry['floorOnly'] ?? false) === true,
                'canSupport' => $shape === 'Box' && ($entry['canSupport'] ?? false) === true,
            ];
        }
        return $lines;
    }

    private static function checkGeometry(array $item, array $box, array $line, array $door, array $bay, array $prior): void
    {
        $dims = [$box['length'], $box['width'], $box['height']];
        $used = [$item['l'], $item['w'], $item['h']];
        sort($dims);
        sort($used);
        foreach ($dims as $index => $dim) if (abs($dim - $used[$index]) > self::EPS) self::invalid();
        if ((!$box['canTilt'] && abs($item['h'] - $box['height']) > self::EPS)
            || ($line['floorOnly'] && $item['z'] > self::EPS)) self::invalid();
        $rear = $door['side'] === 'rear';
        $across = $rear ? $item['y'] : $item['x'];
        $width = $rear ? $item['w'] : $item['l'];
        if ($across < $door['start'] - self::EPS || $across + $width > $door['end'] + self::EPS
            || $item['z'] < $door['sill'] - self::EPS || $item['z'] + $item['h'] > $door['top'] + self::EPS) self::invalid();
        $path = $item;
        if ($rear) {
            $path['x'] += $item['l'];
            $path['l'] = $bay['l'] - $path['x'];
        } elseif ($door['side'] === 'left') {
            $path['w'] = $path['y'];
            $path['y'] = 0.0;
        } else {
            $path['y'] += $item['w'];
            $path['w'] = $bay['w'] - $path['y'];
        }
        $support = 0.0;
        foreach ($prior as $other) {
            if (self::overlap($item, $other) || (min($path['l'], $path['w']) > self::EPS && self::overlap($path, $other))) self::invalid();
            $length = min($item['x'] + $item['l'], $other['x'] + $other['l']) - max($item['x'], $other['x']);
            $width = min($item['y'] + $item['w'], $other['y'] + $other['w']) - max($item['y'], $other['y']);
            if ($length <= self::EPS || $width <= self::EPS) continue;
            if ((!$other['canSupport'] && $other['z'] < $item['z'] - self::EPS)
                || (!$line['canSupport'] && $other['z'] > $item['z'] + self::EPS)) self::invalid();
            if ($other['canSupport'] && abs($item['z'] - $other['z'] - $other['h']) <= self::EPS) $support += $length * $width;
        }
        if ($item['z'] > self::EPS && $support < $item['l'] * $item['w'] - self::EPS) self::invalid();
    }

    private static function overlap(array $a, array $b): bool
    {
        foreach (['x' => 'l', 'y' => 'w', 'z' => 'h'] as $position => $dimension) {
            if ($a[$position] >= $b[$position] + $b[$dimension] - self::EPS
                || $b[$position] >= $a[$position] + $a[$dimension] - self::EPS) return false;
        }
        return true;
    }

    private static function number(mixed $value, float $min, float $max): float
    {
        if ((!is_float($value) && !is_int($value)) || !is_finite((float) $value)
            || $value < $min - self::EPS || $value > $max + self::EPS) self::invalid();
        return (float) $value;
    }

    private static function invalid(): never
    {
        throw new InvalidArgumentException('The proposed load plan is invalid or unreachable in its delivery order. Recalculate and try again.');
    }
}
