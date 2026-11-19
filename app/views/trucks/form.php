<?php
$toFeet = static fn(mixed $cm): string => $cm === null ? '' : rtrim(rtrim(number_format((float) $cm / Truck::CM_PER_FOOT, 4, '.', ''), '0'), '.');
$openingFields = $truck['opening_fields'] ?? array_map(static fn(array $opening): array => [
    'side' => $opening['side'],
    'offset' => $toFeet($opening['offset']),
    'width' => $toFeet($opening['width']),
    'sill' => $toFeet($opening['sill']),
    'height' => $toFeet($opening['height']),
], Truck::openings($truck ?? []));
?>
<div class="page-heading mb-4"><a class="text-decoration-none small" href="<?= e(url('trucks')) ?>">← Back to trucks</a><h1 class="display-6 fw-bold mb-1"><?= $truck ? 'Edit truck' : 'Add truck' ?></h1></div>
<?php if ($errors): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="card border-0 shadow-sm" style="max-width: 850px"><div class="card-body p-4 p-lg-5"><form method="post" action="<?= e(url('truck_save')) ?>">
    <?= csrf_field() ?><?php if ($truck && !empty($truck['id'])): ?><input type="hidden" name="id" value="<?= (int) $truck['id'] ?>"><?php endif; ?>
    <div class="row g-3">
        <div class="col-md-6"><label for="plate_no" class="form-label">Plate number <span class="text-danger">*</span></label><input id="plate_no" name="plate_no" class="form-control" value="<?= e((string) ($truck['plate_no'] ?? '')) ?>" maxlength="25" required></div>
        <div class="col-md-6"><label for="name" class="form-label">Truck name <span class="text-danger">*</span></label><input id="name" name="name" class="form-control" value="<?= e((string) ($truck['name'] ?? '')) ?>" maxlength="120" required></div>
        <div class="col-md-6"><label for="body_type" class="form-label">Body type <span class="text-danger">*</span></label><select id="body_type" name="body_type" class="form-select" required><option value="">Choose body type</option><?php foreach (Truck::BODY_TYPES as $bodyType): ?><option value="<?= e($bodyType) ?>" <?= ($truck['body_type'] ?? '') === $bodyType ? 'selected' : '' ?>><?= e($bodyType) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label for="capacity_kg" class="form-label">Capacity (kg) <span class="text-danger">*</span></label><input id="capacity_kg" name="capacity_kg" type="number" inputmode="decimal" min="0.01" step="0.01" class="form-control" value="<?= e((string) ($truck['capacity_kg'] ?? '')) ?>" required></div>
        <div class="col-md-6">
            <label for="diesel_km_per_liter" class="form-label">Diesel efficiency (km/L)</label>
            <input id="diesel_km_per_liter" name="diesel_km_per_liter" type="number" inputmode="decimal" min="0.10" max="100" step="0.01" class="form-control" value="<?= e((string) ($truck['diesel_km_per_liter'] ?? '')) ?>">
            <div class="form-text">Optional. Use the measured distance divided by liters refilled.</div>
        </div>
        <div class="col-12 mt-4"><h2 class="section-heading mb-1">Usable cargo space</h2><p class="small text-secondary mb-0">A nominal body length is a starting point. Measure the actual clear interior and enter the rated payload for this plate.</p></div>
        <div class="col-md-6">
            <label class="form-label" for="truckPreset">Nominal body length</label>
            <select class="form-select" id="truckPreset"><option value="">Choose a length to start</option><?php foreach (Truck::BODY_LENGTHS_FT as $feet): ?><option value="<?= $feet ?>"><?= $feet ?> ft</option><?php endforeach; ?></select>
        </div>
        <div class="col-md-6 align-self-end"><button type="button" class="btn btn-outline-secondary w-100" id="useTruckPreset">Use length as starting value</button></div>
        <?php foreach (['length', 'width', 'height'] as $axis): $field = "cargo_{$axis}_ft"; ?>
        <div class="col-12 col-sm-4"><label for="<?= $field ?>" class="form-label">Interior <?= $axis ?> (ft)</label><input id="<?= $field ?>" name="<?= $field ?>" type="number" inputmode="decimal" min="0.0002" step="any" class="form-control" value="<?= e((string) ($truck[$field] ?? $toFeet($truck["cargo_{$axis}_cm"] ?? null))) ?>"></div>
        <?php endforeach; ?>
        <div class="col-12 mt-4">
            <h2 class="section-heading mb-1">Loading and unloading openings</h2>
            <p class="small text-secondary mb-2">Add each usable door or open panel. Face forward to identify left and right. Measure a side door's offset from the cab end and a rear door's offset from the left wall.</p>
            <?php if ($truck && !isset($truck['openings_json']) && $openingFields): ?><p class="small text-secondary">The previous opening is shown as a centered rear door. Confirm its position before saving.</p><?php endif; ?>
            <div id="truckOpenings" data-rows="<?= e(json_encode($openingFields, JSON_THROW_ON_ERROR)) ?>"></div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-primary" id="addTruckOpening">Add opening</button>
                <button type="button" class="btn btn-outline-secondary" id="suggestTruckOpenings">Suggest from body type</button>
            </div>
            <p id="openingMessage" class="form-text" role="status"></p>
        </div>
        <div class="col-12"><div class="form-check form-switch"><input id="is_active" name="is_active" type="checkbox" class="form-check-input" <?= !$truck || $truck['is_active'] ? 'checked' : '' ?>><label for="is_active" class="form-check-label">Active truck</label></div></div>
    </div>
    <div class="form-actions mt-4"><button class="btn btn-primary" type="submit"><i class="bi bi-check2 me-1" aria-hidden="true"></i> Save truck</button><a class="btn btn-outline-secondary" href="<?= e(url('trucks')) ?>">Cancel</a></div>
</form></div></div>
<template id="truckOpeningTemplate">
    <fieldset class="border rounded-3 p-3 mb-2">
        <legend class="float-none w-auto px-1 mb-1 h6 opening-heading">Opening</legend>
        <div class="row g-2">
            <div class="col-6"><label class="form-label small">Side<select class="form-select mt-1" data-field="side"><option value="rear">Rear</option><option value="left">Left</option><option value="right">Right</option></select></label></div>
            <div class="col-6 text-end"><button class="btn btn-outline-danger btn-sm remove-opening" type="button">Remove</button></div>
            <?php foreach (['offset' => 'Offset (ft)', 'width' => 'Clear width (ft)', 'sill' => 'Height from floor (ft)', 'height' => 'Clear height (ft)'] as $field => $label): ?>
                <div class="col-6 col-sm-3"><label class="form-label small w-100"><?= $label ?><input data-field="<?= $field ?>" class="form-control mt-1" type="number" inputmode="decimal" step="any" min="0" required></label></div>
            <?php endforeach; ?>
        </div>
    </fieldset>
</template>
<?php $pageModule = 'truck-form.mjs?v=load2'; ?>
