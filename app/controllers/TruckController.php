<?php
declare(strict_types=1);

final class TruckController
{
    public static function index(): void
    {
        require_login();
        render('trucks/index', ['title' => 'Trucks', 'trucks' => Truck::all()]);
    }

    public static function form(): void
    {
        require_admin();
        $id = isset($_GET['id']) ? positive_id($_GET['id']) : null;
        $truck = $id === null ? null : Truck::find($id);
        if ($id !== null && !$truck) {
            throw new RuntimeException('Truck not found.');
        }
        render('trucks/form', ['title' => $id ? 'Edit truck' : 'Add truck', 'truck' => $truck, 'errors' => []]);
    }

    public static function save(): void
    {
        require_admin();
        require_post();
        verify_csrf();
        $id = empty($_POST['id']) ? null : positive_id($_POST['id']);
        if ($id !== null && !Truck::find($id)) {
            throw new RuntimeException('Truck not found.');
        }
        $truck = [
            'id' => $id,
            'plate_no' => strtoupper(trim((string) ($_POST['plate_no'] ?? ''))),
            'name' => trim((string) ($_POST['name'] ?? '')),
            'body_type' => trim((string) ($_POST['body_type'] ?? '')),
            'capacity_kg' => trim((string) ($_POST['capacity_kg'] ?? '')),
            'diesel_km_per_liter' => trim((string) ($_POST['diesel_km_per_liter'] ?? '')),
            'cargo_length_ft' => trim((string) ($_POST['cargo_length_ft'] ?? '')),
            'cargo_width_ft' => trim((string) ($_POST['cargo_width_ft'] ?? '')),
            'cargo_height_ft' => trim((string) ($_POST['cargo_height_ft'] ?? '')),
            'opening_fields' => array_values(array_slice((array) ($_POST['openings'] ?? []), 0, 9)),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
        $errors = [];
        if (!preg_match('/^[A-Z0-9 -]{3,25}$/', $truck['plate_no'])) {
            $errors[] = 'Enter a valid plate number (3–25 letters, numbers, spaces or hyphens).';
        }
        if ($truck['name'] === '' || mb_strlen($truck['name']) > 120) {
            $errors[] = 'Truck name is required and must be at most 120 characters.';
        }
        if (!in_array($truck['body_type'], Truck::BODY_TYPES, true)) {
            $errors[] = 'Choose a valid truck body type.';
        }
        if (!is_numeric($truck['capacity_kg']) || (float) $truck['capacity_kg'] <= 0 || (float) $truck['capacity_kg'] > 99999999.99) {
            $errors[] = 'Capacity must be a positive number of kilograms.';
        }
        $efficiency = $truck['diesel_km_per_liter'];
        if ($efficiency !== '' && (!is_numeric($efficiency) || (float) $efficiency < 0.1 || (float) $efficiency > 100)) {
            $errors[] = 'Enter a measured diesel efficiency between 0.1 and 100 km/L.';
        }
        $filledCargo = 0;
        foreach (['length', 'width', 'height'] as $axis) {
            $value = $truck["cargo_{$axis}_ft"];
            $truck["cargo_{$axis}_cm"] = $value === '' ? null : self::feet($value);
            if ($value !== '') {
                $filledCargo++;
                if ($truck["cargo_{$axis}_cm"] === null) $errors[] = 'Enter a valid interior ' . $axis . ' in feet.';
            }
        }
        if ($filledCargo !== 0 && $filledCargo !== 3) {
            $errors[] = 'Enter all three usable interior measurements, or leave them all blank.';
        }
        $truck['openings'] = self::readOpenings($truck, $errors);
        if ($errors) {
            render('trucks/form', ['title' => $id ? 'Edit truck' : 'Add truck', 'truck' => $truck, 'errors' => $errors]);
            return;
        }
        try {
            $savedId = Truck::save($truck, $id);
            flash('success', $id === null ? 'Truck added.' : 'Truck updated.');
            redirect('truck_form', ['id' => $savedId]);
        } catch (PDOException $error) {
            if ($error->getCode() !== '23000') {
                throw $error;
            }
            render('trucks/form', ['title' => $id ? 'Edit truck' : 'Add truck', 'truck' => $truck, 'errors' => ['Plate number already exists.']]);
        }
    }

    private static function feet(mixed $value, bool $allowZero = false): ?float
    {
        if (!is_numeric($value)) return null;
        $cm = round((float) $value * Truck::CM_PER_FOOT, 2);
        return is_finite($cm) && $cm >= ($allowZero ? 0 : 0.01) && $cm <= 2000 ? $cm : null;
    }

    private static function readOpenings(array $truck, array &$errors): array
    {
        $openings = [];
        if (count($truck['opening_fields']) > 8) $errors[] = 'Save up to eight openings per truck.';
        foreach ($truck['opening_fields'] as $index => $row) {
            $row = (array) $row;
            $side = $row['side'] ?? '';
            $offset = self::feet($row['offset'] ?? '', true);
            $width = self::feet($row['width'] ?? '');
            $sill = self::feet($row['sill'] ?? '', true);
            $height = self::feet($row['height'] ?? '');
            $span = $side === 'rear' ? $truck['cargo_width_cm'] : $truck['cargo_length_cm'];
            if (!in_array($side, ['rear', 'left', 'right'], true) || $offset === null || $width === null
                || $sill === null || $height === null || $span === null || $truck['cargo_height_cm'] === null
                || $offset + $width > $span + .011 || $sill + $height > $truck['cargo_height_cm'] + .011) {
                $errors[] = 'Opening ' . ($index + 1) . ' requires valid measurements within the cargo body.';
                continue;
            }
            $opening = compact('side', 'offset', 'width', 'sill', 'height');
            if (in_array($opening, $openings, true)) $errors[] = 'Remove the repeated opening.';
            $openings[] = $opening;
        }
        return $openings;
    }

    public static function archive(): void
    {
        require_admin();
        require_post();
        verify_csrf();
        Truck::archive(positive_id($_POST['id'] ?? null));
        flash('success', 'Truck archived. You can reactivate it by editing it.');
        redirect('trucks');
    }
}
