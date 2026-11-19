import polyline from 'https://cdn.jsdelivr.net/npm/@mapbox/polyline@1.2.1/+esm';
import { lineString, nearestPointOnLine, point, pointToLineDistance } from 'https://cdn.jsdelivr.net/npm/@turf/turf@7.2.0/+esm';

const mapElement = document.getElementById('forecastMap');
if (mapElement && typeof L !== 'undefined') {
  const branches = JSON.parse(mapElement.dataset.branches || '[]');
  const branchById = new Map(branches.map((branch) => [branch.id, branch]));
  const originSelect = document.getElementById('originBranch');
  const destinationSelect = document.getElementById('destinationBranch');
  const corridorSlider = document.getElementById('corridorKm');
  const corridorValue = document.getElementById('corridorValue');
  const candidateList = document.getElementById('candidateList');
  const candidateCount = document.getElementById('candidateCount');
  const candidateHint = document.getElementById('candidateHint');
  const recalculateButton = document.getElementById('recalculateRoute');
  const message = document.getElementById('forecastMessage');
  const directMetrics = document.getElementById('directMetrics');
  const finalMetrics = document.getElementById('finalMetrics');
  const selectedCount = document.getElementById('selectedCount');
  const cacheBadge = document.getElementById('routeCacheBadge');
  const saveForm = document.getElementById('saveTripForm');
  const saveButton = document.getElementById('saveTripButton');
  const recalculateStatus = document.getElementById('recalculateStatus');
  const saveStatus = document.getElementById('saveTripStatus');
  const truckSelect = document.getElementById('tripTruck');
  const driverField = document.getElementById('tripDriver');
  const dateField = document.getElementById('tripDate');
  const recommendedMax = Number(mapElement.dataset.recommendedMax || 5);
  const marginalMax = Number(mapElement.dataset.marginalMax || 15);

  const warehouse = branches.find(branch => branch.is_origin);
  const map = L.map(mapElement).setView(warehouse ? [warehouse.lat, warehouse.lng] : [14.78, 120.96], warehouse ? 13 : 11);
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
  }).addTo(map);

  const markerLayer = L.layerGroup().addTo(map);
  const routeMemo = new Map();
  const detourMemo = new Map();
  const selectedIds = new Set();
  let requestQueue = Promise.resolve();
  let directRoute = null;
  let finalRoute = null;
  let candidates = [];
  let directLine = null;
  let finalLine = null;
  let directGeneration = 0;
  let estimateGeneration = 0;
  let saving = false;

  const debounce = (callback, delay) => {
    let timer;
    return (...args) => {
      clearTimeout(timer);
      timer = setTimeout(() => callback(...args), delay);
    };
  };

  const showMessage = (text, type = 'info') => {
    message.className = `alert alert-${type} mt-3 mb-0`;
    message.textContent = text;
  };

  const metricsText = (route) => `${(route.distance_m / 1000).toFixed(1)} km · ${Math.round(route.duration_s / 60)} min`;

  const updateSummary = () => {
    directMetrics.textContent = directRoute ? metricsText(directRoute) : '—';
    selectedCount.textContent = String(selectedIds.size);
    finalMetrics.textContent = finalRoute ? metricsText(finalRoute) :
      (selectedIds.size && directRoute ? 'Recalculate to preview' : (directRoute ? metricsText(directRoute) : '—'));
    recalculateButton.disabled = saving || !directRoute || selectedIds.size > 10;
    saveButton.disabled = saving || !directRoute || (selectedIds.size > 0 && !finalRoute) || !truckSelect.value;
    cacheBadge.classList.toggle('d-none', !((finalRoute || directRoute)?.cached));
  };

  const requestRoute = (ids) => {
    const key = ids.join(':');
    if (routeMemo.has(key)) return Promise.resolve(routeMemo.get(key));
    const execute = async () => {
      let response;
      try {
        response = await fetch(mapElement.dataset.proxyUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': mapElement.dataset.csrf },
          credentials: 'same-origin',
          body: JSON.stringify({ branch_ids: ids })
        });
      } catch {
        throw new Error('Could not reach the app server. Check your connection and try again.');
      }
      let payload;
      try {
        payload = await response.json();
      } catch {
        throw new Error('The app server returned an unexpected response. Try signing in again.');
      }
      if (!response.ok) {
        const error = new Error(payload.error || 'Could not load this route.');
        error.status = response.status;
        throw error;
      }
      routeMemo.set(key, payload);
      return payload;
    };
    const queued = requestQueue.then(execute, execute);
    requestQueue = queued.catch(() => {});
    return queued;
  };

  const decodeLatLngs = (geometry) => {
    const points = polyline.decode(geometry);
    if (points.length < 2) throw new Error('The routing service returned an empty route.');
    return points;
  };

  const clearRouteLines = () => {
    if (directLine) map.removeLayer(directLine);
    if (finalLine) map.removeLayer(finalLine);
    directLine = null;
    finalLine = null;
  };

  const drawRoutes = () => {
    clearRouteLines();
    if (!directRoute) return;
    const directLatLngs = decodeLatLngs(directRoute.geometry);
    directLine = L.polyline(directLatLngs, {
      color: '#3276c3', weight: finalRoute ? 4 : 5, opacity: finalRoute ? 0.6 : 0.9,
      dashArray: finalRoute ? '9 8' : null
    }).addTo(map);
    if (finalRoute && finalRoute !== directRoute) {
      finalLine = L.polyline(decodeLatLngs(finalRoute.geometry), { color: '#119082', weight: 6, opacity: 0.9 }).addTo(map);
    }
    map.fitBounds((finalLine || directLine).getBounds(), { padding: [32, 32], maxZoom: 14 });
  };

  const markerPopup = (branch, extra = '') => {
    const box = document.createElement('div');
    const title = document.createElement('strong');
    title.textContent = `${branch.code} · ${branch.name}`;
    const address = document.createElement('div');
    address.textContent = branch.address;
    box.append(title, address);
    if (extra) {
      const detail = document.createElement('small');
      detail.textContent = extra;
      box.append(detail);
    }
    return box;
  };

  const drawMarkers = () => {
    markerLayer.clearLayers();
    const originId = Number(originSelect.value);
    const destinationId = Number(destinationSelect.value);
    for (const id of new Set([originId, destinationId])) {
      const branch = branchById.get(id);
      if (!branch) continue;
      const isOrigin = id === originId;
      L.circleMarker([branch.lat, branch.lng], {
        radius: 9, color: '#fff', weight: 3, fillColor: isOrigin ? '#e69f30' : '#245d9d', fillOpacity: 1
      }).bindPopup(markerPopup(branch, isOrigin ? 'Origin' : 'Main destination')).addTo(markerLayer);
    }
    for (const candidate of candidates) {
      const selected = selectedIds.has(candidate.id);
      L.circleMarker([candidate.lat, candidate.lng], {
        radius: selected ? 8 : 6, color: '#fff', weight: 2,
        fillColor: selected ? '#e69f30' : '#168b77', fillOpacity: 1
      }).bindPopup(markerPopup(candidate, `${candidate.offRouteKm.toFixed(2)} km from route`)).addTo(markerLayer);
    }
  };

  const estimateLabel = (candidate) => {
    if (!candidate.eligible) return ['Not estimated', 'text-secondary'];
    if (candidate.estimateError) return ['Estimate unavailable', 'text-secondary'];
    if (!candidate.estimate) return ['Estimating detour…', 'text-secondary'];
    const extraMin = candidate.estimate.extraMin;
    const category = extraMin < recommendedMax ? ['Recommended', 'recommended'] :
      (extraMin <= marginalMax ? ['Marginal', 'marginal'] : ['Not worth it', 'not-worth-it']);
    return [`+${candidate.estimate.extraKm.toFixed(1)} km · +${extraMin.toFixed(1)} min · ${category[0]}`, `candidate-estimate ${category[1]}`];
  };

  const renderCandidates = () => {
    candidateCount.textContent = String(candidates.length);
    candidateHint.textContent = `${candidates.length} branch${candidates.length === 1 ? '' : 'es'} within ${Number(corridorSlider.value).toFixed(1)} km. Detours are estimated for up to 10 branches nearest the route.`;
    candidateList.replaceChildren();
    if (!directRoute) {
      const empty = document.createElement('div');
      empty.className = 'text-secondary py-4 text-center';
      empty.textContent = 'Waiting for a route.';
      candidateList.append(empty);
      return;
    }
    if (!candidates.length) {
      const empty = document.createElement('div');
      empty.className = 'text-secondary py-4 text-center';
      empty.textContent = 'No branches in this corridor. Widen the slider to look farther from the route.';
      candidateList.append(empty);
      return;
    }
    for (const candidate of candidates) {
      const row = document.createElement('div');
      row.className = 'candidate-row';
      const checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.className = 'form-check-input';
      checkbox.value = String(candidate.id);
      checkbox.checked = selectedIds.has(candidate.id);
      checkbox.setAttribute('aria-label', `Add ${candidate.name} as a stop`);
      const body = document.createElement('div');
      body.className = 'candidate-row-body';
      const title = document.createElement('div');
      title.className = 'fw-semibold';
      title.textContent = `${candidate.code} · ${candidate.name}`;
      const meta = document.createElement('div');
      meta.className = 'candidate-meta';
      meta.textContent = `${candidate.alongKm.toFixed(1)} km along route · ${candidate.offRouteKm.toFixed(2)} km from route`;
      const estimate = document.createElement('div');
      const [label, className] = estimateLabel(candidate);
      estimate.className = `mt-1 ${className}`;
      estimate.textContent = label;
      if (candidate.estimateError) estimate.title = candidate.estimateError;
      body.append(title, meta, estimate);
      row.append(checkbox, body);
      candidateList.append(row);
    }
  };

  const refreshCandidates = () => {
    estimateGeneration += 1;
    if (!directRoute) return;
    const latLngs = decodeLatLngs(directRoute.geometry);
    const line = lineString(latLngs.map(([lat, lng]) => [lng, lat]));
    const originId = Number(originSelect.value);
    const destinationId = Number(destinationSelect.value);
    const corridorKm = Number(corridorSlider.value);
    const matching = [];
    for (const branch of branches) {
      if (branch.id === originId || branch.id === destinationId) continue;
      const location = point([branch.lng, branch.lat]);
      const offRouteKm = Math.max(0, pointToLineDistance(location, line, { units: 'kilometers' }));
      if (offRouteKm > corridorKm) continue;
      const snapped = nearestPointOnLine(line, location, { units: 'kilometers' });
      matching.push({
        ...branch,
        offRouteKm,
        alongKm: snapped.properties.totalDistance ?? snapped.properties.location,
        estimate: detourMemo.get(`${originId}:${branch.id}:${destinationId}`) || null,
        estimateError: null,
        eligible: false
      });
    }
    const nearest = [...matching].sort((a, b) => a.offRouteKm - b.offRouteKm).slice(0, 10);
    const nearestIds = new Set(nearest.map((branch) => branch.id));
    matching.forEach((branch) => { branch.eligible = nearestIds.has(branch.id); });
    candidates = matching.sort((a, b) => a.alongKm - b.alongKm || a.offRouteKm - b.offRouteKm);
    const validIds = new Set(candidates.map((branch) => branch.id));
    for (const id of selectedIds) {
      if (!validIds.has(id)) selectedIds.delete(id);
    }
    finalRoute = null;
    updateSummary();
    drawRoutes();
    drawMarkers();
    renderCandidates();
    void estimateDetours(estimateGeneration);
  };

  const estimateDetours = async (generation) => {
    if (!directRoute) return;
    const originId = Number(originSelect.value);
    const destinationId = Number(destinationSelect.value);
    const direct = directRoute;
    const targets = candidates.filter((candidate) => candidate.eligible && !candidate.estimate);
    if (targets.length && !finalRoute) showMessage('Direct route ready. Estimating nearby detours…', 'info');
    for (const candidate of targets) {
      if (generation !== estimateGeneration) return;
      try {
        const route = await requestRoute([originId, candidate.id, destinationId]);
        if (generation !== estimateGeneration) return;
        const estimate = {
          extraKm: Math.max(0, (route.distance_m - direct.distance_m) / 1000),
          extraMin: Math.max(0, (route.duration_s - direct.duration_s) / 60)
        };
        detourMemo.set(`${originId}:${candidate.id}:${destinationId}`, estimate);
        candidate.estimate = estimate;
      } catch (error) {
        if (generation !== estimateGeneration) return;
        candidate.estimateError = error.message;
        if (error.status === 429 || error.status === 503 || error.status === 401 || error.status === 403) {
          renderCandidates();
          showMessage(error.message, 'warning');
          return;
        }
      }
      renderCandidates();
    }
    if (generation === estimateGeneration && !finalRoute) {
      showMessage(candidates.length ? 'Detour estimates are ready. Select optional stops, then recalculate.' : 'Direct route ready. No branches are in this corridor.', 'success');
    }
  };

  const loadDirect = async () => {
    const generation = ++directGeneration;
    estimateGeneration += 1;
    directRoute = null;
    finalRoute = null;
    candidates = [];
    selectedIds.clear();
    clearRouteLines();
    markerLayer.clearLayers();
    renderCandidates();
    updateSummary();
    const originId = Number(originSelect.value);
    const destinationId = Number(destinationSelect.value);
    drawMarkers();
    if (!branchById.has(originId) || !branchById.has(destinationId)) {
      showMessage(branches.length < 2 ? 'Add a delivery branch before planning a route.' : 'Choose an origin and delivery branch to preview the route.');
      return;
    }
    if (originId === destinationId) {
      showMessage('Choose different origin and destination branches.', 'warning');
      return;
    }
    showMessage('Loading the direct truck route…', 'info');
    try {
      const route = await requestRoute([originId, destinationId]);
      if (generation !== directGeneration) return;
      directRoute = route;
      drawRoutes();
      refreshCandidates();
    } catch (error) {
      if (generation !== directGeneration) return;
      showMessage(error.message, 'warning');
    }
  };

  candidateList.addEventListener('change', (event) => {
    if (saving) return;
    if (event.target.type !== 'checkbox') return;
    const id = Number(event.target.value);
    if (event.target.checked && selectedIds.size >= 10) {
      event.target.checked = false;
      showMessage('A route can include up to 10 optional stops.', 'warning');
      return;
    }
    if (event.target.checked) selectedIds.add(id);
    else selectedIds.delete(id);
    finalRoute = null;
    recalculateStatus.textContent = '';
    saveStatus.textContent = '';
    updateSummary();
    drawRoutes();
    drawMarkers();
  });

  recalculateButton.addEventListener('click', async () => {
    if (saving) return;
    if (!directRoute) return;
    const originId = Number(originSelect.value);
    const destinationId = Number(destinationSelect.value);
    const stops = candidates.filter((candidate) => selectedIds.has(candidate.id)).map((candidate) => candidate.id);
    if (!stops.length) {
      finalRoute = directRoute;
      drawRoutes();
      updateSummary();
      recalculateStatus.textContent = 'Using the direct route.';
      return;
    }
    recalculateButton.disabled = true;
    recalculateStatus.classList.remove('text-danger');
    recalculateStatus.textContent = `Recalculating with ${stops.length} optional stop${stops.length === 1 ? '' : 's'}…`;
    const generation = directGeneration;
    const selection = stops.join(':');
    try {
      const route = await requestRoute([originId, ...stops, destinationId]);
      if (generation !== directGeneration || selection !== candidates.filter((candidate) => selectedIds.has(candidate.id)).map((candidate) => candidate.id).join(':')) return;
      finalRoute = route;
      drawRoutes();
      updateSummary();
      recalculateStatus.textContent = 'Route updated with your selected stops.';
    } catch (error) {
      if (generation === directGeneration) {
        recalculateStatus.classList.add('text-danger');
        recalculateStatus.textContent = error.message;
      }
    } finally {
      updateSummary();
    }
  });

  const reloadDebounced = debounce(loadDirect, 500);
  const onEndpointChange = () => {
    if (saving) return;
    directGeneration += 1;
    estimateGeneration += 1;
    directRoute = null;
    finalRoute = null;
    recalculateStatus.textContent = '';
    saveStatus.textContent = '';
    candidates = [];
    selectedIds.clear();
    clearRouteLines();
    markerLayer.clearLayers();
    renderCandidates();
    updateSummary();
    showMessage('Updating the direct route…', 'info');
    reloadDebounced();
  };
  originSelect.addEventListener('change', onEndpointChange);
  destinationSelect.addEventListener('change', onEndpointChange);
  const refreshDebounced = debounce(() => {
    try { refreshCandidates(); }
    catch (error) { showMessage(`Could not filter branches: ${error.message}`, 'warning'); }
  }, 300);
  corridorSlider.addEventListener('input', () => {
    if (saving) return;
    corridorValue.textContent = `${Number(corridorSlider.value).toFixed(1)} km`;
    finalRoute = null;
    recalculateStatus.textContent = '';
    updateSummary();
    refreshDebounced();
  });

  truckSelect.addEventListener('change', updateSummary);
  saveForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (saving || !saveForm.reportValidity() || !directRoute || (selectedIds.size > 0 && !finalRoute)) return;
    const stopIds = candidates.filter((candidate) => selectedIds.has(candidate.id)).map((candidate) => candidate.id);
    const request = {
      origin_id: Number(originSelect.value),
      destination_id: Number(destinationSelect.value),
      stop_ids: stopIds,
      truck_id: Number(truckSelect.value),
      driver_name: driverField.value.trim(),
      trip_date: dateField.value,
      corridor_km: Number(corridorSlider.value)
    };
    saving = true;
    saveButton.textContent = 'Saving trip…';
    [originSelect, destinationSelect, corridorSlider, truckSelect, driverField, dateField, recalculateButton].forEach((field) => { field.disabled = true; });
    candidateList.querySelectorAll('input[type="checkbox"]').forEach((field) => { field.disabled = true; });
    updateSummary();
    saveStatus.classList.remove('text-danger');
    saveStatus.textContent = 'Saving the planned trip and route…';
    try {
      const response = await fetch(mapElement.dataset.saveUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': mapElement.dataset.csrf },
        credentials: 'same-origin',
        body: JSON.stringify(request)
      });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload.error || 'Could not save the trip.');
      window.location.assign(payload.url);
    } catch (error) {
      saveStatus.classList.add('text-danger');
      saveStatus.textContent = error.message || 'Could not save the trip. Try again.';
      saving = false;
      saveButton.textContent = 'Save planned trip';
      [originSelect, destinationSelect, corridorSlider, truckSelect, driverField, dateField].forEach((field) => { field.disabled = false; });
      candidateList.querySelectorAll('input[type="checkbox"]').forEach((field) => { field.disabled = false; });
      updateSummary();
    }
  });

  void loadDirect();
}
