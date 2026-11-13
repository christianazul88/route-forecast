(() => {
  const element = document.getElementById('branchMap');
  if (!element || typeof L === 'undefined') return;
  const branches = JSON.parse(element.dataset.branches || '[]');
  const map = L.map(element).setView([14.78, 120.96], 10);
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
  }).addTo(map);

  const bounds = [];
  for (const branch of branches) {
    const kind = branch.is_origin ? 'origin' : (branch.is_active ? 'active' : 'inactive');
    const icon = L.divIcon({ className: '', html: `<div class="branch-pin ${kind}"></div>`, iconSize: [19, 19], iconAnchor: [10, 10] });
    const marker = L.marker([branch.lat, branch.lng], { icon }).addTo(map);
    const popup = document.createElement('div');
    const heading = document.createElement('strong');
    heading.textContent = `${branch.code} · ${branch.name}`;
    const address = document.createElement('div');
    address.textContent = branch.address;
    const status = document.createElement('small');
    status.textContent = branch.is_origin ? 'Origin warehouse' : (branch.is_active ? 'Active' : 'Inactive');
    popup.append(heading, address, status);
    marker.bindPopup(popup);
    bounds.push([branch.lat, branch.lng]);
  }
  if (bounds.length) map.fitBounds(bounds, { padding: [35, 35], maxZoom: 13 });
})();
