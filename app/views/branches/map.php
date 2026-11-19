<div class="page-heading d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div><div class="eyebrow mb-2">NETWORK VIEW</div><h1 class="display-6 fw-bold mb-1">Branch map</h1><p class="text-secondary mb-0">Active company locations.</p></div>
    <a class="btn btn-outline-primary" href="<?= e(url('branches')) ?>">List branches</a>
</div>
<div class="card border-0 shadow-sm"><div class="card-body p-3"><div id="branchMap" class="map overview-map" data-branches="<?= e(json_encode(array_map(static fn(array $branch): array => [
    'id' => (int) $branch['id'], 'code' => $branch['code'], 'name' => $branch['name'], 'address' => $branch['address'],
    'lat' => (float) $branch['lat'], 'lng' => (float) $branch['lng'], 'is_active' => (bool) $branch['is_active'], 'is_origin' => (bool) $branch['is_origin'],
], $branches), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR)) ?>"></div></div></div>
<div class="d-flex gap-3 small text-secondary mt-3"><span><span class="legend-dot origin"></span> Warehouse origin</span><span><span class="legend-dot active"></span> Delivery location</span></div>
<?php $pageScript = 'branch-map.js'; ?>
