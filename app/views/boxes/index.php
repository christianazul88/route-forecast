<div class="page-heading d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div><div class="eyebrow mb-2">MASTERFILE</div><h1 class="display-6 fw-bold mb-1">Box types</h1><p class="text-secondary mb-0">Reusable package sizes for load planning.</p></div>
    <?php if (current_user()['role'] === 'Admin'): ?><a class="btn btn-primary" href="<?= e(url('box_form')) ?>">+ Add box type</a><?php endif; ?>
</div>
<div class="card border-0 shadow-sm"><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Code</th><th>Package</th><th>Shape</th><th>Dimensions</th><th>Each</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>
<?php foreach ($boxes as $box): ?><tr>
    <td class="fw-semibold"><?= e($box['code']) ?></td><td><?= e($box['name']) ?></td><td><?= e($box['shape']) ?></td>
    <td class="text-nowrap"><?php if ($box['shape'] === 'Cylinder'): ?>Ø <?= e(number_format((float) $box['length_cm'], 1)) ?> × <?= e(number_format((float) $box['height_cm'], 1)) ?> cm<?php else: ?><?= e(number_format((float) $box['length_cm'], 1)) ?> × <?= e(number_format((float) $box['width_cm'], 1)) ?> × <?= e(number_format((float) $box['height_cm'], 1)) ?> cm<?php endif; ?></td>
    <td><?= e(number_format((float) $box['weight_kg'], 3)) ?> kg</td>
    <td><span class="badge <?= $box['is_active'] ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $box['is_active'] ? 'Active' : 'Inactive' ?></span></td>
    <td class="text-end text-nowrap"><?php if (current_user()['role'] === 'Admin'): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(url('box_form', ['id' => $box['id']])) ?>">Edit</a>
    <?php if ($box['is_active']): ?><form method="post" action="<?= e(url('box_archive')) ?>" class="d-inline" onsubmit="return confirm('Archive this box type?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $box['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Archive</button></form><?php endif; ?>
    <?php else: ?><span class="text-secondary small">View only</span><?php endif; ?></td>
</tr><?php endforeach; ?>
<?php if (!$boxes): ?><tr><td colspan="7" class="text-center text-secondary py-5">No box types yet. Add dimensions for the goods you transport.</td></tr><?php endif; ?>
</tbody></table></div></div></div>
