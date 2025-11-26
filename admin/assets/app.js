// ====================================================
// VisionDrive Admin Panel Interactions (Mockup Match)
// ====================================================

// Select all checkbox
document.querySelectorAll('input[data-selectall]').forEach(master => {
  const tableId = master.getAttribute('data-selectall');
  const table = document.getElementById(tableId);
  if (!table) return;
  master.addEventListener('change', () => {
    const checked = master.checked;
    table.querySelectorAll('tbody input[type="checkbox"]').forEach(cb => {
      cb.checked = checked;
      cb.closest('tr')?.classList.toggle('selected', checked);
    });
  });
});

// Select all button in Dashboard
const btnSelectAll = document.getElementById('btn-select-all');
if (btnSelectAll) {
  btnSelectAll.addEventListener('click', () => {
    const table = document.getElementById('recent-bookings');
    if (!table) return;
    const master = table.querySelector('thead input[type="checkbox"]');
    master.checked = true;
    master.dispatchEvent(new Event('change'));
  });
}

// Search filter
document.querySelectorAll('.toolbar .input[type="search"], .topbar .search').forEach(input => {
  input.addEventListener('input', e => {
    const term = e.target.value.toLowerCase();
    const table = e.target.closest('.content, .main')?.querySelector('.table');
    if (!table) return;
    table.querySelectorAll('tbody tr').forEach(row => {
      const text = row.innerText.toLowerCase();
      row.style.display = text.includes(term) ? '' : 'none';
    });
  });
});

// Highlight selected rows
document.addEventListener('change', e => {
  if (e.target.matches('table input[type="checkbox"]')) {
    const tr = e.target.closest('tr');
    if (tr) tr.classList.toggle('selected', e.target.checked);
  }
});


// Ripple effect on buttons
document.querySelectorAll('.btn').forEach(btn => {
  btn.addEventListener('click', e => {
    const ripple = document.createElement('span');
    ripple.className = 'ripple';
    ripple.style.left = `${e.offsetX}px`;
    ripple.style.top = `${e.offsetY}px`;
    btn.appendChild(ripple);
    setTimeout(() => ripple.remove(), 400);
  });
});

// Sidebar toggle for small screens
const sidebar = document.querySelector('.sidebar');
document.addEventListener('keydown', e => {
  if (e.key === 'Escape' && sidebar?.classList.contains('show')) {
    sidebar.classList.remove('show');
  }
});
// ===== Bulk actions logic =====
// ===== Bulk actions logic (robust) =====
// Bulk inline (show next to search/heading, only Delete)
(function(){
  const box   = document.getElementById('bulk-inline');
  if(!box) return;
  const count = document.getElementById('bulk-inline-count');
  const btn   = document.getElementById('bulk-inline-delete');

  const qSel = () => Array.from(document.querySelectorAll('input[type="checkbox"][name="ids[]"]:checked'));

  function refresh(){
    const n = qSel().length;
    if (count) count.textContent = n;
    box.classList.toggle('show', n > 0);
    box.toggleAttribute('hidden', n === 0);
  }

  // Listen checkbox + select-all
  function onMaybe(e){
    if(e.target.matches('input[type="checkbox"][name="ids[]"], input[data-selectall]')) refresh();
  }
  document.addEventListener('change', onMaybe, true);
  document.addEventListener('click',  onMaybe, true);

  // Delete theo link .row-actions .link.danger
  btn?.addEventListener('click', async ()=>{
    const rows = qSel().map(cb => cb.closest('tr')).filter(Boolean);
    if(!rows.length) return;
    if(!confirm('Delete ' + rows.length + ' selected item(s)?')) return;

    const hrefs = rows.map(tr=>{
      const a = tr.querySelector('.row-actions .link.danger, a.link.danger');
      return a?.href || '';
    }).filter(Boolean);

    if(!hrefs.length){ alert('Không tìm thấy URL xoá.'); return; }

    try{
      for(const url of hrefs){
        await fetch(url, {method:'GET', credentials:'same-origin'});
      }
    }finally{
      location.reload();
    }
  });

  // init
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', refresh);
  } else { refresh(); }
})();
