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
            'capacity_kg' => trim((string) ($_POST['capacity_kg'] ?? '')),
            'cargo_length_cm' => trim((string) ($_POST['cargo_length_cm'] ?? '')),
            'cargo_width_cm' => trim((string) ($_POST['cargo_width_cm'] ?? '')),
            'cargo_height_cm' => trim((string) ($_POST['cargo_height_cm'] ?? '')),
            'door_width_cm' => trim((string) ($_POST['door_width_cm'] ?? '')),
            'door_height_cm' => trim((string) ($_POST['door_height_cm'] ?? '')),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
        $errors = [];
        if (!preg_match('/^[A-Z0-9 -]{3,25}$/', $truck['plate_no'])) {
            $errors[] = 'Enter a valid plate number (3–25 letters, numbers, spaces or hyphens).';
        }
        if ($truck['name'] === '' || mb_strlen($truck['name']) > 120) {
            $errors[] = 'Truck name is required and must be at most 120 characters.';
        }
        if (!is_numeric($truck['capacity_kg']) || (float) $truck['capacity_kg'] <= 0 || (float) $truck['capacity_kg'] > 99999999.99) {
            $errors[] = 'Capacity must be a positive number of kilograms.';
        }
        $cargoFields = ['cargo_length_cm', 'cargo_width_cm', 'cargo_height_cm'];
        $doorFields = ['door_width_cm', 'door_height_cm'];
        foreach (array_merge($cargoFields, $doorFields) as $field) {
            if ($truck[$field] !== '' && (!is_numeric($truck[$field]) || (float) $truck[$field] <= 0 || (float) $truck[$field] > 2000)) {
                $errors[] = 'Cargo and door measurements must be positive centimeters up to 2,000.';
                break;
            }
        }
        $filledCargo = count(array_filter($cargoFields, static fn(string $field): bool => $truck[$field] !== ''));
        $filledDoor = count(array_filter($doorFields, static fn(string $field): bool => $truck[$field] !== ''));
        if ($filledCargo !== 0 && $filledCargo !== 3) {
            $errors[] = 'Enter all three usable interior measurements, or leave them all blank.';
        }
        if ($filledDoor !== 0 && $filledDoor !== 2) {
            $errors[] = 'Enter both door measurements, or leave both blank.';
        }
        if ($filledDoor === 2 && $filledCargo !== 3) {
            $errors[] = 'Enter the cargo space before the door opening.';
        }
        if ($filledDoor === 2 && $filledCargo === 3 &&
            ((float) $truck['door_width_cm'] > (float) $truck['cargo_width_cm'] || (float) $truck['door_height_cm'] > (float) $truck['cargo_height_cm'])) {
            $errors[] = 'The door opening cannot exceed the usable interior width or height.';
        }
        if ($errors) {
            render('trucks/form', ['title' => $id ? 'Edit truck' : 'Add truck', 'truck' => $truck, 'errors' => $errors]);
            return;
        }
        foreach (array_merge($cargoFields, $doorFields) as $field) {
            $truck[$field] = $truck[$field] === '' ? null : $truck[$field];
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
