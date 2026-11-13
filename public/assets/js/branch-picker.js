(() => {
  const mapElement = document.getElementById('branchPickerMap');
  if (!mapElement || typeof L === 'undefined') return;

  const latField = document.getElementById('lat');
  const lngField = document.getElementById('lng');
  const fallback = [14.8342, 120.8808];
  const latitude = Number.parseFloat(latField.value);
  const longitude = Number.parseFloat(lngField.value);
  const hasPosition = Number.isFinite(latitude) && Number.isFinite(longitude);
  const center = hasPosition ? [latitude, longitude] : fallback;

  const map = L.map(mapElement).setView(center, hasPosition ? 15 : 11);
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
  }).addTo(map);

  let marker = null;
  const setPosition = (position) => {
    latField.value = position.lat.toFixed(7);
    lngField.value = position.lng.toFixed(7);
    if (!marker) {
      marker = L.marker(position, { draggable: true }).addTo(map);
      marker.on('dragend', () => setPosition(marker.getLatLng()));
    } else {
      marker.setLatLng(position);
    }
  };

  if (hasPosition) setPosition(L.latLng(latitude, longitude));
  map.on('click', (event) => setPosition(event.latlng));
  const syncFromFields = () => {
    const lat = Number.parseFloat(latField.value);
    const lng = Number.parseFloat(lngField.value);
    if (Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180) {
      setPosition(L.latLng(lat, lng));
      map.panTo([lat, lng]);
    }
  };
  latField.addEventListener('change', syncFromFields);
  lngField.addEventListener('change', syncFromFields);
})();
