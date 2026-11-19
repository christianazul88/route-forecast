(() => {
  const search = document.getElementById('branchSearch');
  if (!search) return;
  const rows = document.querySelectorAll('#branchTable tbody tr:not(#branchSearchEmpty)');
  const empty = document.getElementById('branchSearchEmpty');
  const count = document.getElementById('branchSearchCount');
  search.addEventListener('input', () => {
    const query = search.value.trim().toLocaleLowerCase();
    let visible = 0;
    rows.forEach((row) => {
      row.hidden = !row.textContent.toLocaleLowerCase().includes(query);
      if (!row.hidden) visible += 1;
    });
    empty.hidden = visible > 0;
    count.textContent = `${visible} ${visible === 1 ? 'location' : 'locations'}`;
  });
})();
