<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

$pdo = db();
$pdo->beginTransaction();
try {
    $truckId = Truck::save([
        'plate_no' => 'TEST-LOAD-001',
        'name' => 'Rollback test van',
        'body_type' => 'Closed van',
        'capacity_kg' => '2000',
        'cargo_length_cm' => '480',
        'cargo_width_cm' => '200',
        'cargo_height_cm' => '210',
        'openings' => [['side' => 'rear', 'offset' => 5, 'width' => 190, 'sill' => 0, 'height' => 195]],
        'is_active' => 1,
    ], null);
    $boxId = BoxType::save([
        'code' => 'TEST-LOAD-BOX',
        'name' => 'Rollback test carton',
        'shape' => 'Box',
        'length_cm' => '40',
        'width_cm' => '30',
        'height_cm' => '25',
        'weight_kg' => '5',
        'can_tilt' => 0,
        'is_active' => 1,
    ], null);
    $truck = Truck::find($truckId);
    $box = BoxType::find($boxId);
    if (!$truck || !$box || $truck['body_type'] !== 'Closed van' || (float) $truck['cargo_length_cm'] !== 480.0
        || (float) $box['weight_kg'] !== 5.0 || Truck::openings($truck)[0]['width'] !== 190) {
        throw new RuntimeException('Load planning models did not round-trip through MySQL.');
    }
    echo "Load planning models passed.\n";
} finally {
    $pdo->rollBack();
}
