<?php
require_once 'db.php';
requireLogin();

$filter_dept    = isset($_GET['dept'])    ? (int)$_GET['dept']    : 0;
$filter_sem     = isset($_GET['sem'])     ? (int)$_GET['sem']     : 0;
$filter_year    = sanitize($conn, $_GET['year']    ?? '2025-2026');
$filter_faculty = isset($_GET['faculty']) ? (int)$_GET['faculty'] : 0;
$filter_room    = isset($_GET['room'])    ? (int)$_GET['room']    : 0;

// Delete
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    $conn->query("DELETE FROM timetables WHERE id=$del_id");
    setFlash('success','✅ Entry deleted.');
    header('Location: view_schedule.php?dept='.$filter_dept.'&sem='.$filter_sem.'&year='.urlencode($filter_year));
    exit;
}

$flash = getFlash();
$departments = $conn->query("SELECT * FROM departments ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$faculty_all = $conn->query("SELECT * FROM faculty ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$classrooms  = $conn->query("SELECT * FROM classrooms ORDER BY room_number")->fetch_all(MYSQLI_ASSOC);
$time_slots  = $conn->query("SELECT * FROM time_slots ORDER BY start_time")->fetch_all(MYSQLI_ASSOC);
$days        = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

$grid = []; $timetable_entries = [];

if ($filter_dept || $filter_faculty || $filter_room) {
    $wheres = ["1=1"];
    if ($filter_dept)    $wheres[] = "t.department_id=$filter_dept";
    if ($filter_sem)     $wheres[] = "t.semester=$filter_sem";
    if ($filter_faculty) $wheres[] = "t.faculty_id=$filter_faculty";
    if ($filter_room)    $wheres[] = "t.classroom_id=$filter_room";
    $wheres[] = "t.academic_year='$filter_year'";
    $where = "WHERE " . implode(' AND ', $wheres);

    $q = "SELECT t.*,s.name as subject_name,s.code as subject_code,
                 f.name as faculty_name,c.room_number,
                 ts.label as slot_label,ts.start_time,ts.end_time,d.code as dept_code
          FROM timetables t
          JOIN subjects s ON s.id=t.subject_id
          JOIN faculty f ON f.id=t.faculty_id
          JOIN classrooms c ON c.id=t.classroom_id
          JOIN time_slots ts ON ts.id=t.time_slot_id
          JOIN departments d ON d.id=t.department_id
          $where
          ORDER BY FIELD(t.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'),ts.start_time";
    $res = $conn->query($q);
    if ($res) { while ($row=$res->fetch_assoc()) { $grid[$row['day_of_week']][$row['time_slot_id']]=$row; $timetable_entries[]=$row; } }
}

$colors = ['purple','teal','orange']; $subjectColors = []; $ci = 0;
foreach ($timetable_entries as $e) {
    if (!isset($subjectColors[$e['subject_id']])) { $subjectColors[$e['subject_id']] = $colors[$ci % 3]; $ci++; }
}

$dept_name = '';
foreach ($departments as $d) { if ($d['id']==$filter_dept) { $dept_name=$d['name']; break; } }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>View Schedule — Smart Timetable System</title>
  <meta name="description" content="View weekly timetable grid filtered by department, semester, faculty or room">
  <link rel="stylesheet" href="css/style.css">
  <style>
    .tt-header {
      background:linear-gradient(135deg,rgba(108,99,255,0.12),rgba(0,212,184,0.08));
      border:1px solid var(--border);border-radius:var(--radius);
      padding:18px 24px;margin-bottom:20px;
      display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;
    }
    .filter-btn { background:linear-gradient(135deg,var(--accent),#5a54e8);color:white;border:none;padding:10px 22px;border-radius:var(--radius-sm);font-size:14px;font-weight:600;cursor:pointer;transition:var(--transition); }
    .filter-btn:hover { transform:translateY(-2px);box-shadow:0 6px 20px var(--accent-glow); }
    @media print {
      .sidebar,.topbar,.filter-bar,.action-btns { display:none!important; }
      .main-content { margin-left:0!important; }
      .timetable-grid td,.timetable-grid th { border:1px solid #ccc!important;font-size:11px; }
      body,.app-layout,.main-content,.page-content { background:white!important;color:#000!important; }
    }
  </style>
</head>
<body>
<div class="app-layout">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main-content">
    <div class="topbar">
      <div><div class="topbar-title">📋 Schedule View</div>
        <div class="topbar-subtitle">Weekly timetable grid — filter by department, semester, faculty or room</div>
      </div>
      <div class="topbar-actions">
        <?php if (!empty($timetable_entries)): ?>
        <button onclick="exportPDF()" class="btn btn-outline btn-sm">🖨️ Export PDF</button>
        <?php endif; ?>
        <a href="create_timetable.php" class="btn btn-primary btn-sm">+ Add Entry</a>
      </div>
    </div>

    <div class="page-content">
      <?php if ($flash): ?>
      <div class="alert alert-<?=$flash['type']?>">
        <span class="alert-icon"><?=$flash['type']==='success'?'✅':'❌'?></span>
        <?=htmlspecialchars($flash['msg'])?>
      </div>
      <?php endif; ?>

      <!-- Filter Bar -->
      <div class="filter-bar">
        <select id="f-dept" class="form-control" style="max-width:230px">
          <option value="">🏛️ Department</option>
          <?php foreach($departments as $d): ?>
          <option value="<?=$d['id']?>" <?=$filter_dept==$d['id']?'selected':''?>><?=htmlspecialchars($d['name'])?> (<?=$d['code']?>)</option>
          <?php endforeach; ?>
        </select>
        <select id="f-sem" class="form-control" style="max-width:150px">
          <option value="">📘 Semester</option>
          <?php for($s=1;$s<=8;$s++): ?>
          <option value="<?=$s?>" <?=$filter_sem==$s?'selected':''?>>Semester <?=$s?></option>
          <?php endfor; ?>
        </select>
        <select id="f-year" class="form-control" style="max-width:150px">
          <?php foreach(['2025-2026','2024-2025','2026-2027'] as $y): ?>
          <option value="<?=$y?>" <?=$filter_year===$y?'selected':''?>><?=$y?></option>
          <?php endforeach; ?>
        </select>
        <select id="f-fac" class="form-control" style="max-width:190px">
          <option value="">👨‍🏫 Faculty</option>
          <?php foreach($faculty_all as $f): ?>
          <option value="<?=$f['id']?>" <?=$filter_faculty==$f['id']?'selected':''?>><?=htmlspecialchars($f['name'])?></option>
          <?php endforeach; ?>
        </select>
        <select id="f-room" class="form-control" style="max-width:150px">
          <option value="">🏫 Room</option>
          <?php foreach($classrooms as $c): ?>
          <option value="<?=$c['id']?>" <?=$filter_room==$c['id']?'selected':''?>><?=$c['room_number']?> (<?=$c['building']?>)</option>
          <?php endforeach; ?>
        </select>
        <button onclick="applyFilters()" class="filter-btn">Apply</button>
        <?php if($filter_dept||$filter_faculty||$filter_room): ?>
        <a href="view_schedule.php" class="btn btn-outline btn-sm">Clear</a>
        <?php endif; ?>
      </div>

      <?php if (!$filter_dept && !$filter_faculty && !$filter_room): ?>
      <!-- Welcome State -->
      <div class="card">
        <div style="text-align:center;padding:70px 32px">
          <div style="font-size:64px;margin-bottom:20px">📅</div>
          <h2 style="font-family:'Outfit',sans-serif;margin-bottom:10px;font-size:24px">Select Filters to View Schedule</h2>
          <p style="color:var(--text-muted);margin-bottom:28px">Choose a department and semester above to load the timetable grid</p>
          <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
            <a href="create_timetable.php" class="btn btn-primary">➕ Create New Entry</a>
            <a href="faculty_allocation.php" class="btn btn-outline">👨‍🏫 Faculty Allocation</a>
          </div>
        </div>
      </div>

      <?php else: ?>

      <?php if ($filter_dept && $filter_sem): ?>
      <div class="tt-header">
        <div>
          <div style="font-size:16px;font-weight:700"><?=htmlspecialchars($dept_name)?> — Semester <?=$filter_sem?></div>
          <div style="font-size:12px;color:var(--text-muted);margin-top:3px">
            <?=htmlspecialchars($filter_year)?> &nbsp;·&nbsp; <?=count($timetable_entries)?> entries
          </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <span class="badge badge-purple">🟣 DSA / Core</span>
          <span class="badge badge-teal">🟢 Elective</span>
          <span class="badge badge-orange">🟠 Lab</span>
        </div>
      </div>
      <?php endif; ?>

      <!-- Timetable Grid -->
      <div class="card" id="timetable-print-area">
        <?php if (empty($timetable_entries)): ?>
        <div class="empty-state">
          <div class="empty-icon">📭</div>
          <h3>No entries found for these filters</h3>
          <p><a href="create_timetable.php" style="color:var(--accent)">Create a new entry</a> to get started.</p>
        </div>
        <?php else: ?>

        <!-- Grid View -->
        <div class="timetable-container" style="margin-bottom:32px">
          <table class="timetable-grid">
            <thead>
              <tr>
                <th style="min-width:95px">⏰ Time</th>
                <?php foreach($days as $day): ?><th><?=$day?></th><?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
            <?php foreach($time_slots as $ts): ?>
            <tr>
              <td class="time-col">
                <div style="font-weight:700;font-size:12px"><?=$ts['label']?></div>
                <div style="font-size:10px;color:var(--text-muted);margin-top:2px">
                  <?=date('h:i A',strtotime($ts['start_time']))?><br><?=date('h:i A',strtotime($ts['end_time']))?>
                </div>
              </td>
              <?php foreach($days as $day): ?>
              <td>
                <?php if (isset($grid[$day][$ts['id']])): $e=$grid[$day][$ts['id']];
                  $cls=($subjectColors[$e['subject_id']]??'purple').'-cell'; ?>
                <div class="cell-card <?=$cls?>">
                  <div class="cell-subject"><?=htmlspecialchars($e['subject_code'])?></div>
                  <div class="cell-faculty">👤 <?=htmlspecialchars(explode(' ',$e['faculty_name'])[0])?></div>
                  <div class="cell-room">🚪 <?=htmlspecialchars($e['room_number'])?></div>
                  <div style="display:flex;gap:3px;margin-top:5px">
                    <a href="create_timetable.php?edit=<?=$e['id']?>" class="btn btn-outline btn-sm" style="padding:2px 6px;font-size:10px">✏️</a>
                    <a href="#" onclick="confirmDelete('view_schedule.php?delete=<?=$e['id']?>&dept=<?=$filter_dept?>&sem=<?=$filter_sem?>&year=<?=urlencode($filter_year)?>','<?=htmlspecialchars($e['subject_name'])?>')" class="btn btn-danger btn-sm" style="padding:2px 6px;font-size:10px">🗑️</a>
                  </div>
                </div>
                <?php else: ?><div class="cell-empty">—</div><?php endif; ?>
              </td>
              <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- List View -->
        <hr class="divider">
        <div class="section-header">
          <div class="section-title" style="font-size:15px">📃 List View</div>
          <div class="search-input-wrap" style="max-width:260px">
            <span class="search-icon">🔍</span>
            <input type="text" id="table-search" class="form-control search-input" placeholder="Search...">
          </div>
        </div>
        <div class="table-wrapper">
          <table class="data-table">
            <thead><tr><th>#</th><th>Day</th><th>Time Slot</th><th>Subject</th><th>Faculty</th><th>Room</th><th>Section</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach($timetable_entries as $i=>$e): ?>
            <tr>
              <td style="color:var(--text-muted)"><?=$i+1?></td>
              <td><span class="badge badge-purple"><?=$e['day_of_week']?></span></td>
              <td style="white-space:nowrap;font-size:12px">
                <?=$e['slot_label']?><br>
                <span style="color:var(--text-muted);font-size:10px"><?=date('h:i A',strtotime($e['start_time']))?> – <?=date('h:i A',strtotime($e['end_time']))?></span>
              </td>
              <td>
                <div style="font-weight:600;color:var(--accent-light)"><?=htmlspecialchars($e['subject_name'])?></div>
                <div style="font-size:11px;color:var(--text-muted)"><?=$e['subject_code']?></div>
              </td>
              <td><?=htmlspecialchars($e['faculty_name'])?></td>
              <td><span class="badge badge-teal"><?=$e['room_number']?></span></td>
              <td><span class="badge badge-orange">Sec <?=$e['class_section']?></span></td>
              <td>
                <div style="display:flex;gap:6px">
                  <a href="create_timetable.php?edit=<?=$e['id']?>" class="btn btn-outline btn-sm">✏️ Edit</a>
                  <a href="#" onclick="confirmDelete('view_schedule.php?delete=<?=$e['id']?>&dept=<?=$filter_dept?>&sem=<?=$filter_sem?>&year=<?=urlencode($filter_year)?>','<?=htmlspecialchars($e['subject_name'])?>')" class="btn btn-danger btn-sm">🗑️</a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="js/app.js"></script>
<script>
function applyFilters() {
  const d=document.getElementById('f-dept').value,
        s=document.getElementById('f-sem').value,
        y=document.getElementById('f-year').value,
        f=document.getElementById('f-fac').value,
        r=document.getElementById('f-room').value;
  let url='view_schedule.php?';
  if(d) url+='dept='+d+'&'; if(s) url+='sem='+s+'&';
  if(y) url+='year='+encodeURIComponent(y)+'&';
  if(f) url+='faculty='+f+'&'; if(r) url+='room='+r+'&';
  window.location.href=url;
}
</script>
</body>
</html>
