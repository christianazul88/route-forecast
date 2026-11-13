import polyline from 'https://cdn.jsdelivr.net/npm/@mapbox/polyline@1.2.1/+esm';

const element = document.getElementById('savedRouteMap');
if (element && typeof L !== 'undefined') {
  const origin = JSON.parse(element.dataset.origin || '{}');
  const stops = JSON.parse(element.dataset.stops || '[]');
  const map = L.map(element).setView([14.78, 120.96], 11);
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
  }).addTo(map);

  const popup = (code, name, label) => {
    const container = document.createElement('div');
    const heading = document.createElement('strong');
    heading.textContent = `${code} · ${name}`;
    const detail = document.createElement('div');
    detail.textContent = label;
    container.append(heading, detail);
    return container;
  };

  if (Number.isFinite(origin.lat) && Number.isFinite(origin.lng)) {
    L.circleMarker([origin.lat, origin.lng], { radius: 9, color: '#fff', weight: 3, fillColor: '#e69f30', fillOpacity: 1 })
      .bindPopup(popup(origin.code, origin.name, 'Origin')).addTo(map);
  }
  for (const stop of stops) {
    L.circleMarker([stop.lat, stop.lng], {
      radius: stop.is_main ? 9 : 7, color: '#fff', weight: 3,
      fillColor: stop.is_main ? '#245d9d' : '#168b77', fillOpacity: 1
    }).bindPopup(popup(stop.code, stop.name, stop.is_main ? 'Main destination' : `Stop ${stop.order}`)).addTo(map);
  }
  try {
    const coordinates = polyline.decode(element.dataset.geometry);
    if (coordinates.length < 2) throw new Error('Empty saved route');
    const line = L.polyline(coordinates, { color: '#119082', weight: 6, opacity: 0.9 }).addTo(map);
    map.fitBounds(line.getBounds(), { padding: [35, 35], maxZoom: 14 });
  } catch {
    const notice = L.control({ position: 'topright' });
    notice.onAdd = () => {
      const box = L.DomUtil.create('div', 'map-error');
      box.textContent = 'Saved route could not be drawn.';
      return box;
    };
    notice.addTo(map);
  }
}
