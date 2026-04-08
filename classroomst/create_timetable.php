<?php
require_once 'db.php';
requireLogin();

$flash = getFlash();
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit_data = null;

// ---- Load edit data ----
if ($edit_id) {
    $r = $conn->query("SELECT * FROM timetables WHERE id = $edit_id");
    $edit_data = $r ? $r->fetch_assoc() : null;
}

// ---- Handle POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dept_id    = (int)$_POST['department_id'];
    $semester   = (int)$_POST['semester'];
    $acad_year  = sanitize($conn, $_POST['academic_year']);
    $day        = sanitize($conn, $_POST['day_of_week']);
    $slot_id    = (int)$_POST['time_slot_id'];
    $subject_id = (int)$_POST['subject_id'];
    $faculty_id = (int)$_POST['faculty_id'];
    $room_id    = (int)$_POST['classroom_id'];
    $section    = sanitize($conn, $_POST['class_section']);
    $eid        = (int)($_POST['edit_id'] ?? 0);

    // Conflict check
    $conflicts = [];

    // Faculty double booking
    $fq = "SELECT t.id, s.name as subject, d.code, t.class_section
           FROM timetables t
           JOIN subjects s ON s.id=t.subject_id
           JOIN departments d ON d.id=t.department_id
           WHERE t.faculty_id=$faculty_id AND t.day_of_week='$day'
             AND t.time_slot_id=$slot_id AND t.academic_year='$acad_year'
             AND t.id != $eid";
    $fres = $conn->query($fq);
    if ($fres && $fres->num_rows > 0) {
        $row = $fres->fetch_assoc();
        $conflicts[] = "Faculty already assigned to {$row['subject']} ({$row['code']}-{$row['class_section']}) at this time.";
    }

    // Classroom overlap
    $cq = "SELECT t.id, s.name as subject, d.code, t.class_section
           FROM timetables t
           JOIN subjects s ON s.id=t.subject_id
           JOIN departments d ON d.id=t.department_id
           WHERE t.classroom_id=$room_id AND t.day_of_week='$day'
             AND t.time_slot_id=$slot_id AND t.academic_year='$acad_year'
             AND t.id != $eid";
    $cres = $conn->query($cq);
    if ($cres && $cres->num_rows > 0) {
        $row = $cres->fetch_assoc();
        $conflicts[] = "Classroom already booked for {$row['subject']} ({$row['code']}-{$row['class_section']}) at this time.";
    }

    // Subject clash (same dept for same day/slot)
    $sq = "SELECT id FROM timetables
           WHERE department_id=$dept_id AND semester=$semester
             AND class_section='$section' AND day_of_week='$day'
             AND time_slot_id=$slot_id AND academic_year='$acad_year'
             AND id != $eid";
    $sres = $conn->query($sq);
    if ($sres && $sres->num_rows > 0) {
        $conflicts[] = "This class-section already has a subject scheduled at this time slot.";
    }

    if (!empty($conflicts)) {
        setFlash('error', '⚠️ Conflict detected: ' . implode(' | ', $conflicts));
        header("Location: create_timetable.php" . ($eid ? "?edit=$eid" : ''));
        exit;
    }

    if ($eid) {
        $sql = "UPDATE timetables SET
            department_id=$dept_id, semester=$semester, academic_year='$acad_year',
            day_of_week='$day', time_slot_id=$slot_id, subject_id=$subject_id,
            faculty_id=$faculty_id, classroom_id=$room_id, class_section='$section'
            WHERE id=$eid";
    } else {
        $sql = "INSERT INTO timetables
            (department_id,semester,academic_year,day_of_week,time_slot_id,subject_id,faculty_id,classroom_id,class_section)
            VALUES ($dept_id,$semester,'$acad_year','$day',$slot_id,$subject_id,$faculty_id,$room_id,'$section')";
    }

    if ($conn->query($sql)) {
        setFlash('success', $eid ? '✅ Timetable entry updated successfully!' : '✅ Timetable entry created successfully!');
        header('Location: view_schedule.php?dept=' . $dept_id . '&sem=' . $semester . '&year=' . urlencode($acad_year));
        exit;
    } else {
        setFlash('error', '❌ Database error: ' . $conn->error);
        header('Location: create_timetable.php');
        exit;
    }
}

// ---- Load dropdown data ----
$departments = $conn->query("SELECT * FROM departments ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$time_slots  = $conn->query("SELECT * FROM time_slots ORDER BY start_time")->fetch_all(MYSQLI_ASSOC);
$classrooms  = $conn->query("SELECT * FROM classrooms ORDER BY room_number")->fetch_all(MYSQLI_ASSOC);
$faculty_all = $conn->query("SELECT f.*, d.code as dept_code FROM faculty f LEFT JOIN departments d ON d.id=f.department_id ORDER BY f.name")->fetch_all(MYSQLI_ASSOC);
$subjects_all= $conn->query("SELECT s.*, d.code as dept_code FROM subjects s LEFT JOIN departments d ON d.id=s.department_id ORDER BY s.name")->fetch_all(MYSQLI_ASSOC);

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
$years = ['2025-2026','2024-2025','2026-2027'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $edit_id ? 'Edit' : 'Create' ?> Timetable — Smart Timetable System</title>
  <meta name="description" content="Create or edit timetable entries with real-time conflict detection">
  <link rel="stylesheet" href="css/style.css">
  <style>
    .form-section { margin-bottom:28px; }
    .form-section-title {
      font-size:13px;font-weight:700;color:var(--text-secondary);
      text-transform:uppercase;letter-spacing:1px;
      padding-bottom:12px;border-bottom:1px solid var(--border-light);margin-bottom:20px;
      display:flex;align-items:center;gap:8px;
    }
    .day-selector { display:flex;gap:10px;flex-wrap:wrap; }
    .day-btn {
      padding:10px 18px;border-radius:var(--radius-sm);border:1px solid var(--border-light);
      background:var(--bg-secondary);color:var(--text-secondary);font-size:13px;font-weight:600;
      cursor:pointer;transition:var(--transition);
    }
    .day-btn:hover { border-color:var(--accent);color:var(--accent); }
    .day-btn.selected { background:rgba(108,99,255,0.15);border-color:var(--accent);color:var(--accent-light); }
    .slot-grid { display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px; }
    .slot-btn {
      padding:12px 8px;border-radius:var(--radius-sm);border:1px solid var(--border-light);
      background:var(--bg-secondary);color:var(--text-secondary);font-size:12px;font-weight:500;
      cursor:pointer;transition:var(--transition);text-align:center;
    }
    .slot-btn:hover { border-color:var(--teal);color:var(--teal); }
    .slot-btn.selected { background:rgba(0,212,184,0.1);border-color:var(--teal);color:var(--teal); }
    .slot-btn .slot-time { font-size:10px;color:var(--text-muted);margin-top:3px; }
    #conflict-box { margin-top:16px; }
    .submit-area { display:flex;gap:12px;justify-content:flex-end;margin-top:24px;flex-wrap:wrap; }
    .preview-bar {
      background:var(--bg-secondary);border:1px solid var(--border-light);border-radius:var(--radius-sm);
      padding:16px;margin-top:20px;font-size:13px;color:var(--text-secondary);
      display:flex;gap:20px;flex-wrap:wrap;align-items:center;
    }
    .preview-item { display:flex;flex-direction:column;gap:2px; }
    .preview-item strong { color:var(--text-primary);font-size:14px; }
    .preview-label { font-size:10px;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted); }
  </style>
</head>
<body>
<div class="app-layout">
  <?php include 'includes/sidebar.php'; ?>

  <div class="main-content">
    <div class="topbar">
      <div>
        <div class="topbar-title"><?= $edit_id ? '✏️ Edit' : '➕ Create' ?> Timetable Entry</div>
        <div class="topbar-subtitle">Fill all fields — conflicts are checked in real time</div>
      </div>
      <div class="topbar-actions">
        <a href="view_schedule.php" class="btn btn-outline btn-sm">← Back to Schedule</a>
      </div>
    </div>

    <div class="page-content">
      <?php if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] ?>">
        <span class="alert-icon"><?= $flash['type']==='success'?'✅':'❌'?></span>
        <?= htmlspecialchars($flash['msg']) ?>
      </div>
      <?php endif; ?>

      <form method="POST" action="" id="timetable-form">
        <input type="hidden" name="edit_id" value="<?= $edit_id ?>">

        <div class="card">
          <!-- Section 1: Class Info -->
          <div class="form-section">
            <div class="form-section-title">🏛️ Class Information</div>
            <div class="form-grid">
              <div class="form-group">
                <label for="department_id">Department *</label>
                <select name="department_id" id="department_id" class="form-control" required onchange="filterSubjects()">
                  <option value="">Select Department</option>
                  <?php foreach ($departments as $d): ?>
                  <option value="<?= $d['id'] ?>" <?= ($edit_data['department_id']??'')==$d['id']?'selected':'' ?>>
                    <?= htmlspecialchars($d['name']) ?> (<?= $d['code'] ?>)
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="semester">Semester *</label>
                <select name="semester" id="semester" class="form-control" required>
                  <option value="">Select Semester</option>
                  <?php for($s=1;$s<=8;$s++): ?>
                  <option value="<?=$s?>" <?= ($edit_data['semester']??'')==$s?'selected':'' ?>>Semester <?=$s?></option>
                  <?php endfor; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="class_section">Section *</label>
                <select name="class_section" id="class_section" class="form-control" required>
                  <?php foreach(['A','B','C','D'] as $sec): ?>
                  <option value="<?=$sec?>" <?= ($edit_data['class_section']??'A')==$sec?'selected':'' ?>>Section <?=$sec?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="academic_year">Academic Year *</label>
                <select name="academic_year" id="academic_year" class="form-control" required>
                  <?php foreach($years as $y): ?>
                  <option value="<?=$y?>" <?= ($edit_data['academic_year']??'2025-2026')==$y?'selected':'' ?>><?=$y?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>

          <hr class="divider">

          <!-- Section 2: Day -->
          <div class="form-section">
            <div class="form-section-title">📆 Day of Week *</div>
            <input type="hidden" name="day_of_week" id="day_of_week" value="<?= htmlspecialchars($edit_data['day_of_week']??'') ?>" required>
            <div class="day-selector">
              <?php foreach ($days as $d): ?>
              <button type="button" class="day-btn <?= ($edit_data['day_of_week']??'')===$d?'selected':'' ?>"
                      onclick="selectDay('<?=$d?>', this)"><?= $d ?></button>
              <?php endforeach; ?>
            </div>
          </div>

          <hr class="divider">

          <!-- Section 3: Time Slot -->
          <div class="form-section">
            <div class="form-section-title">⏰ Time Slot *</div>
            <input type="hidden" name="time_slot_id" id="time_slot_id" value="<?= (int)($edit_data['time_slot_id']??0) ?>" required>
            <div class="slot-grid">
              <?php foreach ($time_slots as $ts): ?>
              <button type="button" class="slot-btn <?= ($edit_data['time_slot_id']??0)==$ts['id']?'selected':'' ?>"
                      onclick="selectSlot(<?=$ts['id']?>, this)">
                <?= htmlspecialchars($ts['label']) ?>
                <div class="slot-time"><?= date('h:i A', strtotime($ts['start_time'])) ?> – <?= date('h:i A', strtotime($ts['end_time'])) ?></div>
              </button>
              <?php endforeach; ?>
            </div>
          </div>

          <hr class="divider">

          <!-- Section 4: Subject, Faculty, Room -->
          <div class="form-section">
            <div class="form-section-title">📚 Subject, Faculty & Classroom *</div>
            <div class="form-grid">
              <div class="form-group">
                <label for="subject_id">Subject *</label>
                <select name="subject_id" id="subject_id" class="form-control" required>
                  <option value="">Select Subject</option>
                  <?php foreach ($subjects_all as $s): ?>
                  <option value="<?=$s['id']?>" data-dept="<?=$s['department_id']?>"
                          <?= ($edit_data['subject_id']??'')==$s['id']?'selected':'' ?>>
                    <?= htmlspecialchars($s['name']) ?> (<?= $s['code'] ?>)
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="faculty_id">Faculty *</label>
                <select name="faculty_id" id="faculty_id" class="form-control" required onchange="checkConflicts()">
                  <option value="">Select Faculty</option>
                  <?php foreach ($faculty_all as $f): ?>
                  <option value="<?=$f['id']?>" <?= ($edit_data['faculty_id']??'')==$f['id']?'selected':'' ?>>
                    <?= htmlspecialchars($f['name']) ?> (<?= $f['dept_code'] ?>)
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="classroom_id">Classroom *</label>
                <select name="classroom_id" id="classroom_id" class="form-control" required onchange="checkConflicts()">
                  <option value="">Select Room</option>
                  <?php foreach ($classrooms as $c): ?>
                  <option value="<?=$c['id']?>" <?= ($edit_data['classroom_id']??'')==$c['id']?'selected':'' ?>>
                    <?= $c['room_number'] ?> – <?= $c['building'] ?> (Cap: <?= $c['capacity'] ?>) [<?= $c['room_type'] ?>]
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>

          <!-- Conflict Box -->
          <div class="conflict-box" id="conflict-box"></div>

          <!-- Live Preview -->
          <div class="preview-bar" id="preview-bar" style="display:none">
            <div class="preview-item">
              <span class="preview-label">Day & Time</span>
              <strong id="prev-day">—</strong>
            </div>
            <div class="preview-item">
              <span class="preview-label">Subject</span>
              <strong id="prev-subject">—</strong>
            </div>
            <div class="preview-item">
              <span class="preview-label">Faculty</span>
              <strong id="prev-faculty">—</strong>
            </div>
            <div class="preview-item">
              <span class="preview-label">Room</span>
              <strong id="prev-room">—</strong>
            </div>
          </div>

          <div class="submit-area">
            <a href="view_schedule.php" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary" id="submit-btn">
              <?= $edit_id ? '💾 Update Entry' : '🚀 Create Entry' ?>
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="js/app.js"></script>
<script>
const editId = <?= $edit_id ?>;
window.editId = editId;

function selectDay(day, el) {
  document.getElementById('day_of_week').value = day;
  document.querySelectorAll('.day-btn').forEach(b => b.classList.remove('selected'));
  el.classList.add('selected');
  document.getElementById('prev-day').textContent = day;
  updatePreview();
  checkConflicts();
}

function selectSlot(id, el) {
  document.getElementById('time_slot_id').value = id;
  document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('selected'));
  el.classList.add('selected');
  checkConflicts();
}

function filterSubjects() {
  const deptId = document.getElementById('department_id').value;
  const sel = document.getElementById('subject_id');
  Array.from(sel.options).forEach(opt => {
    if (!opt.value) return;
    opt.style.display = (!deptId || opt.dataset.dept === deptId) ? '' : 'none';
  });
}

function updatePreview() {
  const bar = document.getElementById('preview-bar');
  const day  = document.getElementById('day_of_week').value;
  const subOpt = document.getElementById('subject_id');
  const facOpt = document.getElementById('faculty_id');
  const romOpt = document.getElementById('classroom_id');
  const sub  = subOpt.options[subOpt.selectedIndex]?.text || '—';
  const fac  = facOpt.options[facOpt.selectedIndex]?.text || '—';
  const rom  = romOpt.options[romOpt.selectedIndex]?.text || '—';

  if (day || sub !== '—') {
    bar.style.display = 'flex';
    document.getElementById('prev-day').textContent     = day || '—';
    document.getElementById('prev-subject').textContent = sub;
    document.getElementById('prev-faculty').textContent = fac;
    document.getElementById('prev-room').textContent    = rom;
  }
}

// Hook preview updates
['subject_id','faculty_id','classroom_id'].forEach(id => {
  document.getElementById(id)?.addEventListener('change', updatePreview);
});

// Init filters if editing
filterSubjects();
updatePreview();
</script>
</body>
</html>
