<div class="page-heading mb-4"><a class="text-decoration-none small" href="<?= e(url('trucks')) ?>">← Back to trucks</a><div class="eyebrow mt-3 mb-2">TRUCK MASTERFILE</div><h1 class="display-6 fw-bold mb-1"><?= $truck ? 'Edit truck' : 'Add truck' ?></h1></div>
<?php if ($errors): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="card border-0 shadow-sm" style="max-width: 700px"><div class="card-body p-4 p-lg-5"><form method="post" action="<?= e(url('truck_save')) ?>">
    <?= csrf_field() ?><?php if ($truck && !empty($truck['id'])): ?><input type="hidden" name="id" value="<?= (int) $truck['id'] ?>"><?php endif; ?>
    <div class="row g-3">
        <div class="col-md-6"><label for="plate_no" class="form-label">Plate number <span class="text-danger">*</span></label><input id="plate_no" name="plate_no" class="form-control" value="<?= e((string) ($truck['plate_no'] ?? '')) ?>" maxlength="25" required></div>
        <div class="col-md-6"><label for="name" class="form-label">Truck name <span class="text-danger">*</span></label><input id="name" name="name" class="form-control" value="<?= e((string) ($truck['name'] ?? '')) ?>" maxlength="120" required></div>
        <div class="col-md-6"><label for="capacity_kg" class="form-label">Capacity (kg) <span class="text-danger">*</span></label><input id="capacity_kg" name="capacity_kg" type="number" min="0.01" step="0.01" class="form-control" value="<?= e((string) ($truck['capacity_kg'] ?? '')) ?>" required></div>
        <div class="col-12"><div class="form-check form-switch"><input id="is_active" name="is_active" type="checkbox" class="form-check-input" <?= !$truck || $truck['is_active'] ? 'checked' : '' ?>><label for="is_active" class="form-check-label">Active truck</label></div></div>
    </div>
    <div class="d-flex gap-2 mt-4"><button class="btn btn-primary" type="submit">Save truck</button><a class="btn btn-outline-secondary" href="<?= e(url('trucks')) ?>">Cancel</a></div>
</form></div></div>
