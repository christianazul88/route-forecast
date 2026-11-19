<?php
declare(strict_types=1);

final class AuditController
{
    public static function index(): void
    {
        require_admin('Only admins can view the audit log.');
        $categories = ['all' => 'All activity', 'auth' => 'Sign-ins and sign-outs', 'changes' => 'Saved changes', 'issues' => 'Failed or denied'];
        $submitted = $_GET['category'] ?? '';
        $category = is_string($submitted) && isset($categories[$submitted]) ? $submitted : 'all';
        $before = filter_var($_GET['before'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        $events = AuditLog::recent($category, $before);
        $hasOlder = count($events) > 30;
        if ($hasOlder) array_pop($events);
        render('audit', compact('events', 'category', 'categories', 'before', 'hasOlder') + ['title' => 'Audit log']);
    }

    public static function recordEvent(): void
    {
        require_login();
        require_post();
        verify_csrf();
        $event = $_POST['event'] ?? '';
        $allowed = ['load_estimate_requested', 'capacity_check_requested', 'print_requested'];
        if (!in_array($event, $allowed, true)) {
            http_response_code(422);
            return;
        }
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        audit($event, $event === 'print_requested' ? 'trip' : 'load_plan', $id);
        http_response_code(204);
    }
}
