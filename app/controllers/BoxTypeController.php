<?php
declare(strict_types=1);

final class BoxTypeController
{
    public static function index(): void
    {
        require_login();
        render('boxes/index', ['title' => 'Box types', 'boxes' => BoxType::all()]);
    }

    public static function form(): void
    {
        require_admin();
        $id = isset($_GET['id']) ? positive_id($_GET['id']) : null;
        $box = $id === null ? null : BoxType::find($id);
        if ($id !== null && !$box) {
            throw new RuntimeException('Box type not found.');
        }
        render('boxes/form', ['title' => $id ? 'Edit box type' : 'Add box type', 'box' => $box, 'errors' => []]);
    }

    public static function save(): void
    {
        require_admin();
        require_post();
        verify_csrf();
        $id = empty($_POST['id']) ? null : positive_id($_POST['id']);
        if ($id !== null && !BoxType::find($id)) {
            throw new RuntimeException('Box type not found.');
        }
        $box = [
            'id' => $id,
            'code' => strtoupper(trim((string) ($_POST['code'] ?? ''))),
            'name' => trim((string) ($_POST['name'] ?? '')),
            'shape' => trim((string) ($_POST['shape'] ?? '')),
            'length_cm' => trim((string) ($_POST['length_cm'] ?? '')),
            'width_cm' => trim((string) ($_POST['width_cm'] ?? '')),
            'height_cm' => trim((string) ($_POST['height_cm'] ?? '')),
            'weight_kg' => trim((string) ($_POST['weight_kg'] ?? '')),
            'can_tilt' => isset($_POST['can_tilt']) ? 1 : 0,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
        if ($box['shape'] === 'Cylinder') {
            $box['width_cm'] = $box['length_cm'];
            $box['can_tilt'] = 0;
        }
        $errors = [];
        if (!preg_match('/^[A-Z0-9_-]{2,30}$/', $box['code'])) {
            $errors[] = 'Code must be 2–30 letters, numbers, hyphens, or underscores.';
        }
        if ($box['name'] === '' || mb_strlen($box['name']) > 150) {
            $errors[] = 'Name is required and must be at most 150 characters.';
        }
        if (!in_array($box['shape'], ['Box', 'Cylinder'], true)) {
            $errors[] = 'Select a valid shape.';
        }
        foreach (['length_cm', 'width_cm', 'height_cm'] as $field) {
            if (!is_numeric($box[$field]) || (float) $box[$field] <= 0 || (float) $box[$field] > 2000) {
                $errors[] = 'Dimensions must be positive centimeters up to 2,000.';
                break;
            }
        }
        if (!is_numeric($box['weight_kg']) || (float) $box['weight_kg'] <= 0 || (float) $box['weight_kg'] > 9999999.999) {
            $errors[] = 'Enter a positive weight in kilograms.';
        }
        if ($errors) {
            render('boxes/form', ['title' => $id ? 'Edit box type' : 'Add box type', 'box' => $box, 'errors' => $errors]);
            return;
        }
        try {
            $savedId = BoxType::save($box, $id);
            flash('success', $id === null ? 'Box type added.' : 'Box type updated.');
            redirect('box_form', ['id' => $savedId]);
        } catch (PDOException $error) {
            if ($error->getCode() !== '23000') {
                throw $error;
            }
            render('boxes/form', ['title' => $id ? 'Edit box type' : 'Add box type', 'box' => $box, 'errors' => ['Code already exists.']]);
        }
    }

    public static function archive(): void
    {
        require_admin();
        require_post();
        verify_csrf();
        BoxType::archive(positive_id($_POST['id'] ?? null));
        flash('success', 'Box type archived. You can reactivate it by editing it.');
        redirect('boxes');
    }
}
