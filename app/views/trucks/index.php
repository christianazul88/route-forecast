<div class="page-heading d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div><div class="eyebrow mb-2">MASTERFILE</div><h1 class="display-6 fw-bold mb-1">Trucks</h1><p class="text-secondary mb-0">Vehicles available for delivery trips.</p></div>
    <?php if (current_user()['role'] === 'Admin'): ?><a class="btn btn-primary" href="<?= e(url('truck_form')) ?>">+ Add truck</a><?php endif; ?>
</div>
<div class="card border-0 shadow-sm"><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Plate no.</th><th>Truck</th><th>Body type</th><th>Capacity</th><th>Usable interior</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>
<?php foreach ($trucks as $truck): ?><tr>
    <td class="fw-semibold"><?= e($truck['plate_no']) ?></td><td><?= e($truck['name']) ?></td><td><?= e($truck['body_type'] ?: 'Not set') ?></td><td><?= e(number_format((float) $truck['capacity_kg'], 2)) ?> kg</td>
    <td class="text-nowrap"><?php if ($truck['cargo_length_cm'] !== null): ?><?= e(implode(' × ', array_map(static fn(string $axis): string => rtrim(rtrim(number_format((float) $truck["cargo_{$axis}_cm"] / Truck::CM_PER_FOOT, 2, '.', ''), '0'), '.'), ['length', 'width', 'height']))) ?> ft<div class="small text-secondary"><?= count(Truck::openings($truck)) ?> openings</div><?php else: ?><span class="text-secondary">Not measured</span><?php endif; ?></td>
    <td><span class="badge <?= $truck['is_active'] ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $truck['is_active'] ? 'Active' : 'Inactive' ?></span></td>
    <td class="text-end text-nowrap"><?php if (current_user()['role'] === 'Admin'): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(url('truck_form', ['id' => $truck['id']])) ?>">Edit</a>
    <?php if ($truck['is_active']): ?><form method="post" action="<?= e(url('truck_archive')) ?>" class="d-inline" onsubmit="return confirm('Archive this truck?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $truck['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Archive</button></form><?php endif; ?>
    <?php else: ?><span class="text-secondary small">View only</span><?php endif; ?></td>
</tr><?php endforeach; ?>
<?php if (!$trucks): ?><tr><td colspan="7" class="text-center text-secondary py-5">No trucks yet. Add the first truck to get started.</td></tr><?php endif; ?>
</tbody></table></div></div></div>
