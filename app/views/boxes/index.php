<div class="page-heading d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div><h1 class="display-6 fw-bold mb-1">Box types</h1><p class="text-secondary mb-0">Reusable package sizes for load planning.</p></div>
    <?php if (current_user()['role'] === 'Admin'): ?><a class="btn btn-primary" href="<?= e(url('box_form')) ?>">+ Add box type</a><?php endif; ?>
</div>
<div class="card border-0 shadow-sm"><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0 stacked-table" role="table"><thead role="rowgroup"><tr role="row"><th scope="col" role="columnheader">Code</th><th scope="col" role="columnheader">Package</th><th scope="col" role="columnheader">Shape</th><th scope="col" role="columnheader">Dimensions</th><th scope="col" role="columnheader">Each</th><th scope="col" role="columnheader">Status</th><th scope="col" role="columnheader" class="text-end">Actions</th></tr></thead><tbody role="rowgroup">
<?php foreach ($boxes as $box): ?><tr role="row">
    <td role="cell" class="fw-semibold" data-label="Code"><?= e($box['code']) ?></td><td role="cell" class="record-main"><?= e($box['name']) ?></td><td role="cell" data-label="Shape"><?= e(BoxType::SHAPES[$box['shape']] ?? $box['shape']) ?></td>
    <td role="cell" class="text-nowrap" data-label="Dimensions"><?php if ($box['shape'] === 'Cylinder'): ?>Ø <?= e(number_format((float) $box['length_cm'], 1)) ?> × <?= e(number_format((float) $box['height_cm'], 1)) ?> cm<?php else: ?><?= e(number_format((float) $box['length_cm'], 1)) ?> × <?= e(number_format((float) $box['width_cm'], 1)) ?> × <?= e(number_format((float) $box['height_cm'], 1)) ?> cm<?php endif; ?></td>
    <td role="cell" data-label="Weight each"><?= e(number_format((float) $box['weight_kg'], 3)) ?> kg</td>
    <td role="cell" data-label="Status"><span class="badge <?= $box['is_active'] ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $box['is_active'] ? 'Active' : 'Inactive' ?></span></td>
    <td role="cell" class="record-actions text-end text-nowrap"><?php if (current_user()['role'] === 'Admin'): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(url('box_form', ['id' => $box['id']])) ?>" aria-label="Edit record" title="Edit"><i class="bi bi-pencil" aria-hidden="true"></i><span class="d-none d-md-inline"> Edit</span></a>
    <?php if ($box['is_active']): ?><form method="post" action="<?= e(url('box_archive')) ?>" class="d-inline" onsubmit="return confirm('Archive this box type?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $box['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Archive record" title="Archive"><i class="bi bi-archive" aria-hidden="true"></i><span class="d-none d-md-inline"> Archive</span></button></form><?php endif; ?>
    <?php else: ?><span class="text-secondary small">View only</span><?php endif; ?></td>
</tr><?php endforeach; ?>
<?php if (!$boxes): ?><tr role="row"><td role="cell" colspan="7" class="text-center text-secondary py-5">No box types yet. Add dimensions for the goods you transport.</td></tr><?php endif; ?>
</tbody></table></div></div></div>
