(() => {
  const search = document.getElementById('branchSearch');
  const rows = document.querySelectorAll('#branchTable tbody tr');
  if (!search) return;
  search.addEventListener('input', () => {
    const query = search.value.trim().toLocaleLowerCase();
    rows.forEach((row) => { row.hidden = !row.textContent.toLocaleLowerCase().includes(query); });
  });
})();
