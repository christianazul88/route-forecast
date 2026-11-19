<div class="page-heading d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div>
        <div class="eyebrow mb-2">MASTERFILE</div>
        <h1 class="display-6 fw-bold mb-1"><?= $archived ? 'Archived branches' : 'Branches' ?></h1>
        <p class="text-secondary mb-0"><?= $archived ? 'Inactive locations retained for trip history.' : 'Delivery locations available for route planning.' ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(url('branches', $archived ? [] : ['archived' => 1])) ?>"><?= $archived ? 'Active branches' : 'Archived branches' ?></a>
        <a class="btn btn-outline-primary" href="<?= e(url('branch_map')) ?>">View map</a>
        <?php if (current_user()['role'] === 'Admin'): ?><a class="btn btn-primary" href="<?= e(url('branch_form')) ?>">+ Add branch</a><?php endif; ?>
    </div>
</div>
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="p-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <strong><?= count($branches) ?> locations</strong>
            <input id="branchSearch" class="form-control search-box" type="search" placeholder="Search branches" aria-label="Search branches">
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="branchTable">
                <thead><tr><th>Code</th><th>Branch</th><th>Contact</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($branches as $branch): ?>
                <tr>
                    <td class="fw-semibold text-nowrap"><?= e($branch['code']) ?></td>
                    <td><div class="fw-semibold"><?= e($branch['name']) ?><?php if ($branch['is_origin']): ?> <span class="badge text-bg-info">Origin</span><?php endif; ?></div><div class="small text-secondary"><?= e($branch['address']) ?></div></td>
                    <td><div><?= e($branch['contact_person'] ?: '—') ?></div><div class="small text-secondary"><?= e($branch['contact_no']) ?></div></td>
                    <td><span class="badge <?= $branch['is_active'] ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $branch['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                    <td class="text-end text-nowrap">
                        <?php if (current_user()['role'] === 'Admin'): ?>
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('branch_form', ['id' => $branch['id']])) ?>">Edit</a>
                        <?php if ($branch['is_active'] && !$branch['is_origin']): ?>
                        <form action="<?= e(url('branch_archive')) ?>" method="post" class="d-inline" onsubmit="return confirm('Archive this branch?')">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $branch['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit">Archive</button>
                        </form>
                        <?php endif; ?>
                        <?php else: ?><span class="text-secondary small">View only</span><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="<?= e(asset('js/table-filter.js')) ?>" defer></script>
