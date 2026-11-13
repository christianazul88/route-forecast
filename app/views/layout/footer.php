</main>
<footer class="container-xl pb-4 small text-secondary d-flex justify-content-between">
    <span>Route Forecast · Internal logistics planning</span>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.9/dist/js/bootstrap.bundle.min.js" integrity="sha384-WyOxWvTyr1QaMPfiPJSv76iReVthorl5uWlFtZPyK0IEB0hGzo9y5Bp9mYanr8Bu" crossorigin="anonymous"></script>
<?php if (!empty($mapAssets)): ?>
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="anonymous"></script>
<?php endif; ?>
<?php if (!empty($pageScript)): ?>
<script src="<?= e(asset('js/' . $pageScript)) ?>" defer></script>
<?php endif; ?>
<?php if (!empty($pageModule)): ?>
<script type="module" src="<?= e(asset('js/' . $pageModule)) ?>"></script>
<?php endif; ?>
</body>
</html>
