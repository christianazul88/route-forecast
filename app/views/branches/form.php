<div class="page-heading mb-4">
    <a class="text-decoration-none small" href="<?= e(url('branches')) ?>">← Back to branches</a>
    <h1 class="display-6 fw-bold mb-1"><?= $branch ? 'Edit branch' : 'Add branch' ?></h1>
    <p class="text-secondary mb-0">Enter the receiving address and pin the truck entrance.</p>
</div>
<?php if ($errors): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" action="<?= e(url('branch_save')) ?>">
    <?= csrf_field() ?>
    <?php if ($branch && !empty($branch['id'])): ?><input type="hidden" name="id" value="<?= (int) $branch['id'] ?>"><?php endif; ?>
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm"><div class="card-body p-4">
                <h2 class="section-heading">Branch details</h2>
                <div class="row g-3">
                    <div class="col-md-5"><label for="code" class="form-label">Code <span class="text-danger">*</span></label><input id="code" name="code" class="form-control" value="<?= e((string) ($branch['code'] ?? '')) ?>" maxlength="30" required></div>
                    <div class="col-md-7"><label for="name" class="form-label">Name <span class="text-danger">*</span></label><input id="name" name="name" class="form-control" value="<?= e((string) ($branch['name'] ?? '')) ?>" maxlength="150" required></div>
                    <div class="col-12"><label for="address" class="form-label">Address <span class="text-danger">*</span></label><textarea id="address" name="address" class="form-control" rows="2" maxlength="255" required><?= e((string) ($branch['address'] ?? '')) ?></textarea></div>
                    <div class="col-md-6"><label for="contact_person" class="form-label">Contact person</label><input id="contact_person" name="contact_person" class="form-control" value="<?= e((string) ($branch['contact_person'] ?? '')) ?>" maxlength="120" autocomplete="name"></div>
                    <div class="col-md-6"><label for="contact_no" class="form-label">Contact number</label><input id="contact_no" name="contact_no" type="tel" class="form-control" value="<?= e((string) ($branch['contact_no'] ?? '')) ?>" maxlength="40" autocomplete="tel"></div>
                    <div class="col-12"><div class="form-check form-switch"><input id="is_active" name="is_active" class="form-check-input" type="checkbox" <?= !$branch || $branch['is_active'] ? 'checked' : '' ?>><label for="is_active" class="form-check-label">Active branch</label></div></div>
                    <div class="col-12"><div class="form-check form-switch"><input id="is_origin" name="is_origin" class="form-check-input" type="checkbox" <?= $branch && $branch['is_origin'] ? 'checked' : '' ?>><label for="is_origin" class="form-check-label">Default warehouse origin</label></div><div class="form-text">Selecting this branch removes the origin flag from the current warehouse.</div></div>
                </div>
            </div></div>
        </div>
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm"><div class="card-body p-3">
                <h2 class="section-heading"><i class="bi bi-geo-alt text-primary" aria-hidden="true"></i> Map pin</h2>
                <p class="form-text mb-2">Tap the map, drag the pin, or enter latitude and longitude below.</p>
                <div id="branchPickerMap" class="map picker-map"></div>
                <div class="row g-2 mt-2">
                    <div class="col-6"><label for="lat" class="form-label">Latitude <span class="text-danger">*</span></label><input id="lat" name="lat" type="number" inputmode="decimal" step="0.0000001" min="-90" max="90" class="form-control" value="<?= e((string) ($branch['lat'] ?? '')) ?>" required></div>
                    <div class="col-6"><label for="lng" class="form-label">Longitude <span class="text-danger">*</span></label><input id="lng" name="lng" type="number" inputmode="decimal" step="0.0000001" min="-180" max="180" class="form-control" value="<?= e((string) ($branch['lng'] ?? '')) ?>" required></div>
                </div>
            </div></div>
        </div>
    </div>
    <div class="form-actions mt-4"><button class="btn btn-primary" type="submit"><i class="bi bi-check2 me-1" aria-hidden="true"></i> Save branch</button><a class="btn btn-outline-secondary" href="<?= e(url('branches')) ?>">Cancel</a></div>
</form>
<?php $pageScript = 'branch-picker.js'; ?>
