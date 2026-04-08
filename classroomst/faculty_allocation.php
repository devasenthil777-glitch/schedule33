<?php
require_once 'db.php';
requireLogin();

$filter_dept = isset($_GET['dept']) ? (int)$_GET['dept'] : 0;
$filter_year = sanitize($conn, $_GET['year'] ?? '2025-2026');
$flash = getFlash();

$departments = $conn->query("SELECT * FROM departments ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Faculty list with workload stats
$where = $filter_dept ? "WHERE f.department_id = $filter_dept" : "";
$faculty_list = $conn->query("
    SELECT f.*, d.name as dept_name, d.code as dept_code,
           COUNT(t.id) as sessions_scheduled,
           f.max_hours_per_week,
           GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') as subjects_assigned
    FROM faculty f
    LEFT JOIN departments d ON d.id = f.department_id
    LEFT JOIN timetables t ON t.faculty_id = f.id AND t.academic_year = '$filter_year'
    LEFT JOIN allocations a ON a.faculty_id = f.id AND a.academic_year = '$filter_year'
    LEFT JOIN subjects s ON s.id = a.subject_id
    $where
    GROUP BY f.id
    ORDER BY sessions_scheduled DESC
")->fetch_all(MYSQLI_ASSOC);

// All subjects for allocation form
$subjects_all = $conn->query("SELECT s.*, d.code as dept_code FROM subjects s LEFT JOIN departments d ON d.id=s.department_id ORDER BY s.name")->fetch_all(MYSQLI_ASSOC);

// Handle allocation POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['allocate'])) {
    $fid = (int)$_POST['faculty_id'];
    $sid = (int)$_POST['subject_id'];
    $sem = (int)$_POST['semester'];
    $yr  = sanitize($conn, $_POST['acad_year']);

    // Check max workload
    $curr = $conn->query("SELECT COUNT(*) c FROM timetables WHERE faculty_id=$fid AND academic_year='$yr'")->fetch_assoc()['c'];
    $max  = $conn->query("SELECT max_hours_per_week FROM faculty WHERE id=$fid")->fetch_assoc()['max_hours_per_week'];

    if ($curr >= $max) {
        setFlash('error', '⚠️ Faculty has reached maximum weekly hours (' . $max . '). Cannot allocate more.');
    } else {
        $ins = $conn->query("INSERT IGNORE INTO allocations (faculty_id, subject_id, semester, academic_year) VALUES ($fid,$sid,$sem,'$yr')");
        if ($ins) {
            setFlash('success', '✅ Subject allocated to faculty successfully!');
        } else {
            setFlash('error', '❌ Allocation already exists or error: ' . $conn->error);
        }
    }
    header('Location: faculty_allocation.php?dept=' . $filter_dept . '&year=' . urlencode($filter_year));
    exit;
}

// Handle deallocation
if (isset($_GET['deallocate'])) {
    $aid = (int)$_GET['deallocate'];
    $conn->query("DELETE FROM allocations WHERE id=$aid");
    setFlash('success', '✅ Allocation removed.');
    header('Location: faculty_allocation.php?dept=' . $filter_dept . '&year=' . urlencode($filter_year));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Faculty Allocation — Smart Timetable System</title>
  <meta name="description" content="Assign subjects to faculty, manage workloads and prevent over-allocation">
  <link rel="stylesheet" href="css/style.css">
  <style>
    .faculty-card {
      background:var(--bg-card);border:1px solid var(--border-light);
      border-radius:var(--radius);padding:20px;margin-bottom:16px;
      transition:var(--transition);
    }
    .faculty-card:hover { border-color:var(--border);transform:translateY(-2px);box-shadow:var(--shadow-card); }
    .faculty-header { display:flex;align-items:center;gap:14px;margin-bottom:14px; }
    .faculty-avatar {
      width:48px;height:48px;border-radius:14px;
      background:linear-gradient(135deg,var(--accent),var(--pink));
      display:flex;align-items:center;justify-content:center;
      font-weight:700;font-size:18px;color:white;flex-shrink:0;
    }
    .faculty-meta { flex:1; }
    .faculty-name { font-size:15px;font-weight:700;color:var(--text-primary); }
    .faculty-dept { font-size:12px;color:var(--text-muted);margin-top:2px; }
    .faculty-stats { display:flex;gap:12px;flex-wrap:wrap; }
    .load-chip {
      font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;
    }
    .load-ok    { background:rgba(46,204,113,0.12);color:var(--green); }
    .load-warn  { background:rgba(243,156,18,0.12);color:var(--yellow); }
    .load-danger{ background:rgba(231,76,60,0.12);color:var(--red); }
    .subjects-list { display:flex;gap:8px;flex-wrap:wrap;margin-top:10px; }
    .subject-chip {
      display:inline-flex;align-items:center;gap:6px;
      padding:4px 12px;border-radius:20px;font-size:12px;
      background:rgba(108,99,255,0.1);border:1px solid rgba(108,99,255,0.2);color:var(--accent-light);
    }
    .subject-chip a { color:var(--red);font-size:14px;line-height:1;text-decoration:none; }
    .subject-chip a:hover { opacity:0.7; }
    .alloc-panel {
      background:var(--bg-secondary);border:1px solid var(--border-light);
      border-radius:var(--radius);padding:24px;margin-bottom:24px;
    }
  </style>
</head>
<body>
<div class="app-layout">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main-content">
    <div class="topbar">
      <div>
        <div class="topbar-title">👨‍🏫 Faculty Allocation</div>
        <div class="topbar-subtitle">Assign subjects, monitor workloads, prevent over-allocation</div>
      </div>
      <div class="topbar-actions">
        <button onclick="openModal('allocModal')" class="btn btn-primary btn-sm">+ Allocate Subject</button>
      </div>
    </div>

    <div class="page-content">
      <?php if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] ?>">
        <span class="alert-icon"><?= $flash['type']==='success'?'✅':'❌'?></span>
        <?= htmlspecialchars($flash['msg']) ?>
      </div>
      <?php endif; ?>

      <!-- Filter -->
      <div class="filter-bar">
        <select id="filter-faculty-dept" class="form-control" style="max-width:260px">
          <option value="">🏛️ All Departments</option>
          <?php foreach ($departments as $d): ?>
          <option value="<?=$d['id']?>" <?= $filter_dept==$d['id']?'selected':'' ?>>
            <?= htmlspecialchars($d['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <select id="filter-alloc-year" class="form-control" style="max-width:160px">
          <?php foreach(['2025-2026','2024-2025','2026-2027'] as $y): ?>
          <option value="<?=$y?>" <?= $filter_year===$y?'selected':'' ?>><?=$y?></option>
          <?php endforeach; ?>
        </select>
        <button onclick="applyFacultyFilter()" class="btn btn-primary btn-sm">Apply</button>
        <?php if ($filter_dept): ?><a href="faculty_allocation.php" class="btn btn-outline btn-sm">Clear</a><?php endif; ?>
      </div>

      <!-- Summary Stats -->
      <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));margin-bottom:24px">
        <?php
          $total_f = count($faculty_list);
          $overloaded = 0; $normal = 0;
          foreach ($faculty_list as $f) {
            $pct = ($f['max_hours_per_week'] > 0) ? ($f['sessions_scheduled'] / $f['max_hours_per_week']) * 100 : 0;
            if ($pct > 85) $overloaded++;
            else $normal++;
          }
        ?>
        <div class="stat-card purple fade-in">
          <div class="stat-icon purple">👥</div>
          <div class="stat-info">
            <div class="stat-value"><?= $total_f ?></div>
            <div class="stat-label">Total Faculty</div>
          </div>
        </div>
        <div class="stat-card teal fade-in fade-in-2">
          <div class="stat-icon teal">✅</div>
          <div class="stat-info">
            <div class="stat-value"><?= $normal ?></div>
            <div class="stat-label">Normal Workload</div>
          </div>
        </div>
        <div class="stat-card orange fade-in fade-in-3">
          <div class="stat-icon orange">⚠️</div>
          <div class="stat-info">
            <div class="stat-value"><?= $overloaded ?></div>
            <div class="stat-label">Overloaded</div>
          </div>
        </div>
      </div>

      <!-- Faculty Cards -->
      <?php if (empty($faculty_list)): ?>
      <div class="card">
        <div class="empty-state">
          <div class="empty-icon">👥</div>
          <h3>No faculty found</h3>
          <p>Add faculty from <a href="manage_faculty.php" style="color:var(--accent)">Manage Faculty</a></p>
        </div>
      </div>
      <?php else: ?>
      <?php foreach ($faculty_list as $f):
        $sessions = (int)$f['sessions_scheduled'];
        $max = (int)$f['max_hours_per_week'];
        $pct = $max > 0 ? min(round(($sessions / $max) * 100), 100) : 0;
        $loadClass = $pct > 85 ? 'load-danger' : ($pct > 60 ? 'load-warn' : 'load-ok');
        $barClass  = $pct > 85 ? 'danger' : ($pct > 60 ? 'warning' : '');
        $initial   = strtoupper(substr($f['name'], 0, 1));

        // Get allocations for this faculty
        $allocs = $conn->query("
          SELECT a.id, s.name as sub_name, s.code
          FROM allocations a JOIN subjects s ON s.id=a.subject_id
          WHERE a.faculty_id={$f['id']} AND a.academic_year='$filter_year'
        ")->fetch_all(MYSQLI_ASSOC);
      ?>
      <div class="faculty-card fade-in">
        <div class="faculty-header">
          <div class="faculty-avatar"><?= $initial ?></div>
          <div class="faculty-meta">
            <div class="faculty-name"><?= htmlspecialchars($f['name']) ?></div>
            <div class="faculty-dept">
              <?= htmlspecialchars($f['dept_name'] ?? 'N/A') ?>
              <?php if ($f['specialization']): ?>
                &nbsp;·&nbsp; <?= htmlspecialchars($f['specialization']) ?>
              <?php endif; ?>
            </div>
          </div>
          <div class="faculty-stats">
            <span class="load-chip <?= $loadClass ?>"><?= $sessions ?>/<?= $max ?> hrs</span>
            <span class="badge <?= $pct > 85 ? 'badge-red' : ($pct > 60 ? 'badge-yellow' : 'badge-green') ?>">
              <?= $pct ?>% Load
            </span>
          </div>
        </div>

        <div class="workload-bar-wrap">
          <div class="workload-label">
            <span>Weekly sessions scheduled: <?= $sessions ?></span>
            <span>Max: <?= $max ?></span>
          </div>
          <div class="progress-bar">
            <div class="progress-fill <?= $barClass ?>" data-width="<?= $pct ?>" style="width:0"></div>
          </div>
        </div>

        <div class="subjects-list" style="margin-top:14px">
          <?php if (empty($allocs)): ?>
          <span style="font-size:12px;color:var(--text-muted)">No subjects allocated yet.</span>
          <?php else: ?>
          <?php foreach ($allocs as $a): ?>
          <span class="subject-chip">
            📚 <?= htmlspecialchars($a['code']) ?> – <?= htmlspecialchars($a['sub_name']) ?>
            <a href="faculty_allocation.php?deallocate=<?=$a['id']?>&dept=<?=$filter_dept?>&year=<?=urlencode($filter_year)?>"
               onclick="return confirm('Remove this allocation?')" title="Remove">✕</a>
          </span>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div style="margin-top:12px;display:flex;gap:8px">
          <a href="view_schedule.php?faculty=<?=$f['id']?>&year=<?=urlencode($filter_year)?>"
             class="btn btn-outline btn-sm">📋 View Schedule</a>
          <button onclick="prefillAlloc(<?=$f['id']?>, '<?= htmlspecialchars($f['name']) ?>')"
                  class="btn btn-teal btn-sm">+ Allocate Subject</button>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Allocate Modal -->
<div class="modal-overlay" id="allocModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">📚 Allocate Subject to Faculty</div>
      <button class="modal-close" onclick="closeModal('allocModal')">✕</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="allocate" value="1">
      <div class="form-group" style="margin-bottom:16px">
        <label for="modal-faculty">Faculty *</label>
        <select name="faculty_id" id="modal-faculty" class="form-control" required>
          <option value="">Select Faculty</option>
          <?php foreach ($faculty_list as $f): ?>
          <option value="<?=$f['id']?>"><?= htmlspecialchars($f['name']) ?> (<?= $f['dept_code'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin-bottom:16px">
        <label for="modal-subject">Subject *</label>
        <select name="subject_id" id="modal-subject" class="form-control" required>
          <option value="">Select Subject</option>
          <?php foreach ($subjects_all as $s): ?>
          <option value="<?=$s['id']?>"><?= htmlspecialchars($s['name']) ?> (<?=$s['code']?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-grid" style="margin-bottom:16px">
        <div class="form-group">
          <label for="modal-sem">Semester *</label>
          <select name="semester" id="modal-sem" class="form-control" required>
            <?php for($s=1;$s<=8;$s++): ?>
            <option value="<?=$s?>">Semester <?=$s?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="modal-year">Academic Year *</label>
          <select name="acad_year" id="modal-year" class="form-control" required>
            <?php foreach(['2025-2026','2024-2025','2026-2027'] as $y): ?>
            <option value="<?=$y?>" <?= $filter_year===$y?'selected':'' ?>><?=$y?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px">
        <button type="button" onclick="closeModal('allocModal')" class="btn btn-outline">Cancel</button>
        <button type="submit" class="btn btn-primary">✅ Allocate</button>
      </div>
    </form>
  </div>
</div>

<script src="js/app.js"></script>
<script>
function applyFacultyFilter() {
  const dept = document.getElementById('filter-faculty-dept').value;
  const year = document.getElementById('filter-alloc-year').value;
  window.location.href = 'faculty_allocation.php?dept=' + dept + '&year=' + encodeURIComponent(year);
}
function prefillAlloc(fid, name) {
  const sel = document.getElementById('modal-faculty');
  if (sel) sel.value = fid;
  openModal('allocModal');
}
</script>
</body>
</html>
