<?php
declare(strict_types=1);

final class BranchController
{
    public static function index(): void
    {
        require_login();
        $archived = ($_GET['archived'] ?? '') === '1';
        $branches = array_filter(Branch::all(), static fn(array $branch): bool => (bool) $branch['is_active'] !== $archived);
        render('branches/index', ['title' => $archived ? 'Archived branches' : 'Branches', 'branches' => $branches, 'archived' => $archived]);
    }

    public static function map(): void
    {
        require_login();
        render('branches/map', ['title' => 'Branch map', 'branches' => Branch::active(), 'mapAssets' => true]);
    }

    public static function form(): void
    {
        require_admin();
        $id = isset($_GET['id']) ? positive_id($_GET['id']) : null;
        $branch = $id === null ? null : Branch::find($id);
        if ($id !== null && !$branch) {
            throw new RuntimeException('Branch not found.');
        }
        render('branches/form', ['title' => $id ? 'Edit branch' : 'Add branch', 'branch' => $branch, 'errors' => [], 'mapAssets' => true]);
    }

    public static function save(): void
    {
        require_admin();
        require_post();
        verify_csrf();
        $id = empty($_POST['id']) ? null : positive_id($_POST['id']);
        if ($id !== null && !Branch::find($id)) {
            throw new RuntimeException('Branch not found.');
        }
        $branch = [
            'id' => $id,
            'code' => strtoupper(trim((string) ($_POST['code'] ?? ''))),
            'name' => trim((string) ($_POST['name'] ?? '')),
            'address' => trim((string) ($_POST['address'] ?? '')),
            'lat' => trim((string) ($_POST['lat'] ?? '')),
            'lng' => trim((string) ($_POST['lng'] ?? '')),
            'contact_person' => trim((string) ($_POST['contact_person'] ?? '')),
            'contact_no' => trim((string) ($_POST['contact_no'] ?? '')),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'is_origin' => isset($_POST['is_origin']) ? 1 : 0,
        ];
        $errors = [];
        foreach (['code' => 30, 'name' => 150, 'address' => 255] as $key => $length) {
            if ($branch[$key] === '' || mb_strlen($branch[$key]) > $length) {
                $errors[] = ucfirst($key) . " is required and must be at most {$length} characters.";
            }
        }
        if (!preg_match('/^[A-Z0-9_-]+$/', $branch['code'])) {
            $errors[] = 'Code may contain only letters, numbers, hyphens and underscores.';
        }
        if (mb_strlen($branch['contact_person']) > 120 || mb_strlen($branch['contact_no']) > 40) {
            $errors[] = 'Contact details are too long.';
        }
        if (!is_numeric($branch['lat']) || (float) $branch['lat'] < -90 || (float) $branch['lat'] > 90 ||
            !is_numeric($branch['lng']) || (float) $branch['lng'] < -180 || (float) $branch['lng'] > 180) {
            $errors[] = 'Choose valid coordinates on the map.';
        }
        if ($branch['is_origin'] && !$branch['is_active']) {
            $errors[] = 'The origin warehouse must remain active.';
        }
        $existing = $id === null ? null : Branch::find($id);
        if ($existing && $existing['is_origin'] && !$branch['is_origin']) {
            $errors[] = 'Choose a new origin branch before removing this origin designation.';
        }
        if ($errors) {
            render('branches/form', ['title' => $id ? 'Edit branch' : 'Add branch', 'branch' => $branch, 'errors' => $errors, 'mapAssets' => true]);
            return;
        }
        try {
            $savedId = Branch::save($branch, $id);
            flash('success', $id === null ? 'Branch added.' : 'Branch updated.');
            redirect('branch_form', ['id' => $savedId]);
        } catch (PDOException $error) {
            if ($error->getCode() !== '23000') {
                throw $error;
            }
            render('branches/form', ['title' => $id ? 'Edit branch' : 'Add branch', 'branch' => $branch, 'errors' => ['Branch code already exists.'], 'mapAssets' => true]);
        }
    }

    public static function archive(): void
    {
        require_admin();
        require_post();
        verify_csrf();
        Branch::archive(positive_id($_POST['id'] ?? null));
        flash('success', 'Branch archived. You can reactivate it by editing it.');
        redirect('branches');
    }
}
