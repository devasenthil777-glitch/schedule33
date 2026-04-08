// ============================================
// Smart Timetable System - Main JS
// ============================================

document.addEventListener('DOMContentLoaded', () => {

  // --- Flash message auto-hide ---
  const flash = document.querySelector('.alert');
  if (flash) {
    setTimeout(() => {
      flash.style.opacity = '0';
      flash.style.transform = 'translateY(-10px)';
      flash.style.transition = 'all 0.4s ease';
      setTimeout(() => flash.remove(), 400);
    }, 4000);
  }

  // --- Sidebar mobile toggle ---
  const menuBtn = document.getElementById('menu-toggle');
  const sidebar = document.querySelector('.sidebar');
  if (menuBtn && sidebar) {
    menuBtn.addEventListener('click', () => {
      sidebar.classList.toggle('open');
    });
    document.addEventListener('click', (e) => {
      if (!sidebar.contains(e.target) && !menuBtn.contains(e.target)) {
        sidebar.classList.remove('open');
      }
    });
  }

  // --- Active nav highlight ---
  const page = window.location.pathname.split('/').pop() || 'dashboard.php';
  document.querySelectorAll('.nav-item').forEach(item => {
    if (item.getAttribute('href') === page) item.classList.add('active');
  });

  // --- Modal helpers ---
  window.openModal = (id) => {
    document.getElementById(id)?.classList.add('open');
  };
  window.closeModal = (id) => {
    document.getElementById(id)?.classList.remove('open');
  };
  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) overlay.classList.remove('open');
    });
  });

  // --- Animate stats on load ---
  animateCounters();

  // --- Timetable conflict checker ---
  initConflictChecker();

  // --- Table search/filter ---
  initTableSearch();

  // --- Animate progress bars ---
  setTimeout(() => {
    document.querySelectorAll('.progress-fill').forEach(bar => {
      const target = bar.getAttribute('data-width');
      if (target) bar.style.width = target + '%';
    });
  }, 300);

});

// ---- Animated Counters ----
function animateCounters() {
  document.querySelectorAll('.stat-value[data-target]').forEach(el => {
    const target = parseInt(el.getAttribute('data-target'));
    let current = 0;
    const step = Math.ceil(target / 40);
    const interval = setInterval(() => {
      current = Math.min(current + step, target);
      el.textContent = current;
      if (current >= target) clearInterval(interval);
    }, 30);
  });
}

// ---- Conflict Checker (Create Timetable page) ----
function initConflictChecker() {
  const form = document.getElementById('timetable-form');
  if (!form) return;

  const fields = ['faculty_id', 'classroom_id', 'day_of_week', 'time_slot_id', 'department_id', 'semester'];
  fields.forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('change', checkConflicts);
  });
}

function checkConflicts() {
  const faculty   = document.getElementById('faculty_id')?.value;
  const classroom = document.getElementById('classroom_id')?.value;
  const day       = document.getElementById('day_of_week')?.value;
  const slot      = document.getElementById('time_slot_id')?.value;
  const dept      = document.getElementById('department_id')?.value;
  const sem       = document.getElementById('semester')?.value;

  if (!faculty || !classroom || !day || !slot || !dept || !sem) return;

  const box = document.getElementById('conflict-box');

  fetch('ajax/check_conflict.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `faculty_id=${faculty}&classroom_id=${classroom}&day=${day}&slot_id=${slot}&edit_id=${window.editId || ''}`
  })
  .then(r => r.json())
  .then(data => {
    if (!box) return;
    box.innerHTML = '';
    if (data.conflicts && data.conflicts.length > 0) {
      box.classList.add('visible');
      let html = '<h4>⚠️ Conflicts Detected</h4>';
      data.conflicts.forEach(c => {
        html += `<div class="conflict-item">🔴 ${c}</div>`;
      });
      box.innerHTML = html;
      // Disable submit
      const submitBtn = document.getElementById('submit-btn');
      if (submitBtn) { submitBtn.disabled = true; submitBtn.style.opacity = '0.5'; }
    } else {
      box.classList.remove('visible');
      const submitBtn = document.getElementById('submit-btn');
      if (submitBtn) { submitBtn.disabled = false; submitBtn.style.opacity = '1'; }
    }
  })
  .catch(() => {});
}

// ---- Table Search ----
function initTableSearch() {
  const searchInput = document.getElementById('table-search');
  const table = document.querySelector('.data-table tbody');
  if (!searchInput || !table) return;

  searchInput.addEventListener('input', () => {
    const q = searchInput.value.toLowerCase();
    table.querySelectorAll('tr').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}

// ---- Timetable filter dropdowns ----
function filterTimetable() {
  const dept = document.getElementById('filter-dept')?.value;
  const sem  = document.getElementById('filter-sem')?.value;
  const year = document.getElementById('filter-year')?.value || '2025-2026';
  if (!dept || !sem) return;
  window.location.href = `view_schedule.php?dept=${dept}&sem=${sem}&year=${year}`;
}

// ---- Faculty filter ----
function filterFaculty() {
  const dept = document.getElementById('filter-faculty-dept')?.value;
  if (!dept) return;
  window.location.href = `faculty_allocation.php?dept=${dept}`;
}

// ---- Delete confirm ----
function confirmDelete(url, name) {
  if (confirm(`Are you sure you want to delete "${name}"? This action cannot be undone.`)) {
    window.location.href = url;
  }
}

// ---- Export timetable as PDF (via print) ----
function exportPDF() {
  const printArea = document.getElementById('timetable-print-area');
  if (!printArea) { window.print(); return; }
  const w = window.open('', '_blank');
  w.document.write(`<html><head><title>Timetable</title>
    <style>
      body{font-family:Arial,sans-serif;padding:20px;color:#000;}
      table{border-collapse:collapse;width:100%;}
      th,td{border:1px solid #ccc;padding:8px;text-align:center;font-size:12px;}
      th{background:#e8e6ff;}
    </style></head><body>${printArea.innerHTML}</body></html>`);
  w.document.close();
  w.focus();
  setTimeout(() => { w.print(); w.close(); }, 500);
}

// ---- Toast notification ----
function showToast(msg, type='success') {
  const t = document.createElement('div');
  t.className = `alert alert-${type} fade-in`;
  t.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;min-width:280px;';
  t.innerHTML = `<span class="alert-icon">${type==='success'?'✅':type==='error'?'❌':'⚠️'}</span>${msg}`;
  document.body.appendChild(t);
  setTimeout(() => { t.style.opacity='0'; setTimeout(()=>t.remove(),400); }, 3500);
}
