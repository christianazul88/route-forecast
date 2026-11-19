<?php
$eventLabels = [
    'login_success' => 'Signed in', 'login_failed' => 'Sign-in failed', 'login_locked' => 'Sign-in rate limited',
    'login_rejected' => 'Sign-in form rejected', 'logout' => 'Signed out', 'setup_admin' => 'Admin account created', 'setup_failed' => 'Admin setup failed',
    'status_change' => 'Status changed', 'create' => 'Created', 'save' => 'Saved', 'archive' => 'Archived', 'record' => 'Recorded',
    'request_failed' => 'Request failed', 'access_denied' => 'Access denied', 'submit' => 'Submitted', 'view' => 'Viewed',
    'change_rejected' => 'Change rejected', 'load_estimate_requested' => 'Load estimate requested',
    'capacity_check_requested' => 'Capacity check requested', 'print_requested' => 'Print requested',
];
$failureEvents = ['login_failed', 'login_locked', 'login_rejected', 'setup_failed', 'request_failed', 'change_rejected', 'access_denied'];
?>
<div class="page-heading mb-4">
    <h1 class="display-6 fw-bold mb-1">Audit log</h1>
    <p class="text-secondary mb-0">Sign-in attempts, activity, and changes to your records. Times are in Manila.</p>
</div>
<div class="card border-0 shadow-sm mb-3"><div class="card-body p-3">
    <form method="get" class="d-flex flex-wrap align-items-end gap-2">
        <input type="hidden" name="page" value="audit_log">
        <div class="record-filter"><label class="form-label" for="auditCategory">Activity</label><select id="auditCategory" name="category" class="form-select">
            <?php foreach ($categories as $value => $label): ?><option value="<?= e($value) ?>" <?= $value === $category ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select></div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-funnel" aria-hidden="true"></i> Filter</button>
        <?php if ($before): ?><a class="btn btn-outline-secondary" href="<?= e(url('audit_log', ['category' => $category])) ?>">Newest</a><?php endif; ?>
    </form>
</div></div>
<div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table align-middle mb-0 stacked-table" role="table" aria-label="Recorded activity">
        <thead role="rowgroup"><tr role="row"><th scope="col">Event</th><th scope="col">When</th><th scope="col">Account</th><th scope="col">Record</th></tr></thead>
        <tbody role="rowgroup">
        <?php foreach ($events as $event):
            $details = json_decode($event['details'] ?? '', true);
            $account = $event['username'] ?? ($details['account'] ?? 'Signed out / system');
            $record = $event['entity_type'] === 'request' ? ($details['page'] ?? 'Page') : $event['entity_type'];
            $failed = in_array($event['action'], $failureEvents, true);
        ?>
            <tr role="row">
                <td class="record-main" role="cell"><span class="audit-event-icon <?= $failed ? 'audit-event-issue' : '' ?>"><i class="bi bi-<?= $failed ? 'exclamation-circle' : 'activity' ?>" aria-hidden="true"></i></span> <?= e($eventLabels[$event['action']] ?? ucfirst(str_replace('_', ' ', $event['action']))) ?></td>
                <td data-label="When" role="cell"><time datetime="<?= e(date(DATE_ATOM, (int) $event['happened_at'])) ?>"><?= e(date('M j, Y · g:i:s A', (int) $event['happened_at'])) ?></time></td>
                <td data-label="Account" role="cell"><?= e((string) $account) ?></td>
                <td data-label="Record" role="cell">
                    <?= e(ucfirst(str_replace('_', ' ', (string) $record))) ?><?= $event['entity_id'] ? ' #' . (int) $event['entity_id'] : '' ?>
                    <?php if (isset($details['http_status'])): ?><span class="small text-secondary">· HTTP <?= (int) $details['http_status'] ?></span><?php endif; ?>
                    <?php if (isset($details['from'], $details['to'])): ?><div class="small text-secondary"><?= e($details['from']) ?> → <?= e($details['to']) ?></div><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$events): ?><tr role="row"><td role="cell" colspan="4" class="text-center text-secondary py-4">No matching activity.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div></div>
<?php if ($hasOlder): ?>
    <a class="btn btn-outline-primary mt-3" href="<?= e(url('audit_log', ['category' => $category, 'before' => end($events)['id']])) ?>">Older activity <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
<?php endif; ?>
