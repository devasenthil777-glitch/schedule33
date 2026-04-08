<?php
require_once 'db.php';
requireLogin();

// ---- Stats ----
$stats = [];
$stats['faculty']    = $conn->query("SELECT COUNT(*) c FROM faculty")->fetch_assoc()['c'];
$stats['subjects']   = $conn->query("SELECT COUNT(*) c FROM subjects")->fetch_assoc()['c'];
$stats['classrooms'] = $conn->query("SELECT COUNT(*) c FROM classrooms")->fetch_assoc()['c'];
$stats['classes']    = $conn->query("SELECT COUNT(*) c FROM timetables")->fetch_assoc()['c'];

// ---- Departments for chart ----
$depts = $conn->query("
  SELECT d.name, COUNT(t.id) as total
  FROM departments d
  LEFT JOIN timetables t ON t.department_id = d.id
  GROUP BY d.id ORDER BY total DESC
")->fetch_all(MYSQLI_ASSOC);

// ---- Recent Timetable Entries ----
$recent = $conn->query("
  SELECT t.day_of_week, ts.label as time_label, s.name as subject,
         f.name as faculty, c.room_number, d.code as dept, t.semester, t.class_section
  FROM timetables t
  JOIN time_slots ts ON ts.id = t.time_slot_id
  JOIN subjects s ON s.id = t.subject_id
  JOIN faculty f ON f.id = t.faculty_id
  JOIN classrooms c ON c.id = t.classroom_id
  JOIN departments d ON d.id = t.department_id
  ORDER BY t.id DESC LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

// ---- Faculty workload ----
$workload = $conn->query("
  SELECT f.name, COUNT(t.id) as sessions
  FROM faculty f
  LEFT JOIN timetables t ON t.faculty_id = f.id
  GROUP BY f.id ORDER BY sessions DESC LIMIT 6
")->fetch_all(MYSQLI_ASSOC);

$flash = getFlash();
$page = 'dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — Smart Timetable System</title>
  <meta name="description" content="Smart Timetable System Dashboard — overview of schedules, faculty, and conflicts">
  <link rel="stylesheet" href="css/style.css">
  <style>
    .dept-bar-item { margin-bottom:14px; }
    .dept-bar-label { display:flex;justify-content:space-between;font-size:13px;color:var(--text-secondary);margin-bottom:6px; }
    .quick-actions { display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:32px; }
    .quick-card {
      background:var(--bg-card);border:1px solid var(--border-light);border-radius:var(--radius);
      padding:20px;display:flex;align-items:center;gap:14px;cursor:pointer;
      transition:var(--transition);text-decoration:none;
    }
    .quick-card:hover { border-color:var(--accent);transform:translateY(-3px);box-shadow:var(--shadow); }
    .quick-card-icon { font-size:26px;width:48px;height:48px;display:flex;align-items:center;justify-content:center;border-radius:12px; }
    .quick-card h4 { font-size:14px;font-weight:600;color:var(--text-primary); }
    .quick-card p { font-size:12px;color:var(--text-muted);margin-top:2px; }
    .day-chip {
      display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;
      background:rgba(108,99,255,0.12);color:var(--accent-light);
    }
  </style>
</head>
<body>
<div class="app-layout">
  <?php include 'includes/sidebar.php'; ?>

  <div class="main-content">
    <!-- Top Bar -->
    <div class="topbar">
      <div>
        <div class="topbar-title">📊 Dashboard</div>
        <div class="topbar-subtitle">Welcome back, <?= htmlspecialchars($_SESSION['username']) ?>! Here's your overview.</div>
      </div>
      <div class="topbar-actions">
        <button class="btn btn-sm" id="menu-toggle" style="display:none">☰</button>
        <a href="create_timetable.php" class="btn btn-primary btn-sm">+ New Entry</a>
      </div>
    </div>

    <div class="page-content">
      <?php if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] ?>">
        <span class="alert-icon"><?= $flash['type'] === 'success' ? '✅' : '❌' ?></span>
        <?= htmlspecialchars($flash['msg']) ?>
      </div>
      <?php endif; ?>

      <!-- Stats -->
      <div class="stats-grid">
        <div class="stat-card purple fade-in fade-in-1">
          <div class="stat-icon purple">👥</div>
          <div class="stat-info">
            <div class="stat-value" data-target="<?= $stats['faculty'] ?>"><?= $stats['faculty'] ?></div>
            <div class="stat-label">Total Faculty</div>
            <div class="stat-change">↑ Active Members</div>
          </div>
        </div>
        <div class="stat-card teal fade-in fade-in-2">
          <div class="stat-icon teal">📚</div>
          <div class="stat-info">
            <div class="stat-value" data-target="<?= $stats['subjects'] ?>"><?= $stats['subjects'] ?></div>
            <div class="stat-label">Total Subjects</div>
            <div class="stat-change">↑ Across departments</div>
          </div>
        </div>
        <div class="stat-card orange fade-in fade-in-3">
          <div class="stat-icon orange">🏫</div>
          <div class="stat-info">
            <div class="stat-value" data-target="<?= $stats['classrooms'] ?>"><?= $stats['classrooms'] ?></div>
            <div class="stat-label">Classrooms</div>
            <div class="stat-change">↑ Rooms available</div>
          </div>
        </div>
        <div class="stat-card pink fade-in fade-in-4">
          <div class="stat-icon pink">📅</div>
          <div class="stat-info">
            <div class="stat-value" data-target="<?= $stats['classes'] ?>"><?= $stats['classes'] ?></div>
            <div class="stat-label">Scheduled Classes</div>
            <div class="stat-change">↑ This semester</div>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="section-header"><div class="section-title">⚡ Quick Actions</div></div>
      <div class="quick-actions">
        <a href="create_timetable.php" class="quick-card fade-in fade-in-1">
          <div class="quick-card-icon" style="background:rgba(108,99,255,0.1);color:var(--accent)">➕</div>
          <div><h4>Create Entry</h4><p>Add a new timetable slot</p></div>
        </a>
        <a href="view_schedule.php" class="quick-card fade-in fade-in-2">
          <div class="quick-card-icon" style="background:rgba(0,212,184,0.1);color:var(--teal)">📋</div>
          <div><h4>View Schedule</h4><p>Browse timetable grid</p></div>
        </a>
        <a href="faculty_allocation.php" class="quick-card fade-in fade-in-3">
          <div class="quick-card-icon" style="background:rgba(255,107,53,0.1);color:var(--orange)">👨‍🏫</div>
          <div><h4>Faculty Allocation</h4><p>Manage workloads</p></div>
        </a>
        <a href="manage_faculty.php" class="quick-card fade-in fade-in-4">
          <div class="quick-card-icon" style="background:rgba(255,77,166,0.1);color:var(--pink)">🗂️</div>
          <div><h4>Manage Faculty</h4><p>Add/edit faculty records</p></div>
        </a>
      </div>

      <div class="grid-2">
        <!-- Recent Entries -->
        <div class="card fade-in">
          <div class="card-header">
            <div class="card-title"><span class="icon">🕓</span> Recent Schedule Entries</div>
            <a href="view_schedule.php" class="btn btn-outline btn-sm">View All</a>
          </div>
          <div class="table-wrapper">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Day</th><th>Period</th><th>Subject</th><th>Faculty</th><th>Room</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($recent)): ?>
                <tr><td colspan="5"><div class="empty-state"><div class="empty-icon">📭</div><h3>No entries yet</h3></div></td></tr>
                <?php else: ?>
                <?php foreach ($recent as $r): ?>
                <tr>
                  <td><span class="day-chip"><?= $r['day_of_week'] ?></span></td>
                  <td><?= htmlspecialchars($r['time_label']) ?></td>
                  <td style="font-weight:600;color:var(--accent-light)"><?= htmlspecialchars($r['subject']) ?></td>
                  <td><?= htmlspecialchars($r['faculty']) ?></td>
                  <td><span class="badge badge-teal"><?= htmlspecialchars($r['room_number']) ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Faculty Workload -->
        <div class="card fade-in fade-in-2">
          <div class="card-header">
            <div class="card-title"><span class="icon">📊</span> Faculty Workload</div>
            <a href="faculty_allocation.php" class="btn btn-outline btn-sm">Details</a>
          </div>
          <?php foreach ($workload as $i => $w):
            $pct = min(round(($w['sessions'] / 14) * 100), 100);
            $cls = $pct > 85 ? 'danger' : ($pct > 60 ? 'warning' : '');
          ?>
          <div class="dept-bar-item">
            <div class="dept-bar-label">
              <span><?= htmlspecialchars($w['name']) ?></span>
              <span style="color:var(--text-muted)"><?= $w['sessions'] ?> sessions</span>
            </div>
            <div class="progress-bar">
              <div class="progress-fill <?= $cls ?>" data-width="<?= $pct ?>" style="width:0"></div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($workload)): ?>
          <div class="empty-state"><div class="empty-icon">📭</div><h3>No data yet</h3></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Department Distribution -->
      <div class="card fade-in" style="margin-top:24px">
        <div class="card-header">
          <div class="card-title"><span class="icon">🏛️</span> Department Schedule Load</div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;">
          <?php foreach ($depts as $i => $d):
            $colors = ['var(--accent)', 'var(--teal)', 'var(--orange)', 'var(--pink)'];
            $color  = $colors[$i % count($colors)];
          ?>
          <div style="text-align:center;padding:20px;background:var(--bg-secondary);border-radius:12px;">
            <div style="font-size:32px;font-weight:800;color:<?= $color ?>;font-family:'Outfit',sans-serif">
              <?= $d['total'] ?>
            </div>
            <div style="font-size:13px;color:var(--text-secondary);margin-top:4px"><?= htmlspecialchars($d['name']) ?></div>
            <div style="font-size:11px;color:var(--text-muted)">scheduled classes</div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

    </div><!-- /page-content -->
  </div><!-- /main-content -->
</div>

<script src="js/app.js"></script>
</body>
</html>
