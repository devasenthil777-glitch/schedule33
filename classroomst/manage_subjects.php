<?php
require_once 'db.php';
requireLogin();
$flash = getFlash();

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM subjects WHERE id=$id");
    setFlash('success','✅ Subject deleted.'); header('Location: manage_subjects.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id   = (int)($_POST['id']??0);
    $name = sanitize($conn,$_POST['name']);
    $code = sanitize($conn,$_POST['code']);
    $dept = (int)$_POST['department_id'];
    $sem  = (int)$_POST['semester'];
    $cred = (int)$_POST['credits'];
    $hpw  = (int)$_POST['hours_per_week'];
    if ($id) {
        $sql = "UPDATE subjects SET name='$name',code='$code',department_id=$dept,semester=$sem,credits=$cred,hours_per_week=$hpw WHERE id=$id";
        $msg = '✅ Subject updated!';
    } else {
        $sql = "INSERT INTO subjects (name,code,department_id,semester,credits,hours_per_week) VALUES ('$name','$code',$dept,$sem,$cred,$hpw)";
        $msg = '✅ Subject added!';
    }
    if ($conn->query($sql)) setFlash('success',$msg);
    else setFlash('error','❌ '.$conn->error);
    header('Location: manage_subjects.php'); exit;
}

$departments = $conn->query("SELECT * FROM departments ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$subjects = $conn->query("SELECT s.*,d.name as dept_name,d.code as dept_code FROM subjects s LEFT JOIN departments d ON d.id=s.department_id ORDER BY s.name")->fetch_all(MYSQLI_ASSOC);
$edit = null;
if (isset($_GET['edit'])) { $r=$conn->query("SELECT * FROM subjects WHERE id=".(int)$_GET['edit']); $edit=$r?$r->fetch_assoc():null; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Subjects — Smart Timetable System</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="app-layout">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main-content">
    <div class="topbar">
      <div><div class="topbar-title">📚 Manage Subjects</div><div class="topbar-subtitle">Add, edit or remove subjects</div></div>
      <div class="topbar-actions"><button onclick="openModal('subModal')" class="btn btn-primary btn-sm">+ Add Subject</button></div>
    </div>
    <div class="page-content">
      <?php if ($flash): ?>
      <div class="alert alert-<?=$flash['type']?>"><span class="alert-icon"><?=$flash['type']==='success'?'✅':'❌'?></span><?=htmlspecialchars($flash['msg'])?></div>
      <?php endif; ?>
      <div class="filter-bar">
        <div class="search-input-wrap"><span class="search-icon">🔍</span>
        <input type="text" id="table-search" class="form-control search-input" placeholder="Search subjects..."></div>
      </div>
      <div class="card">
        <div class="table-wrapper">
          <table class="data-table">
            <thead><tr><th>#</th><th>Name</th><th>Code</th><th>Department</th><th>Semester</th><th>Credits</th><th>Hrs/Week</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if(empty($subjects)): ?><tr><td colspan="8"><div class="empty-state"><div class="empty-icon">📚</div><h3>No subjects added yet</h3></div></td></tr>
              <?php else: foreach($subjects as $i=>$s): ?>
              <tr>
                <td style="color:var(--text-muted)"><?=$i+1?></td>
                <td style="font-weight:600;color:var(--accent-light)"><?=htmlspecialchars($s['name'])?></td>
                <td><span class="badge badge-purple"><?=htmlspecialchars($s['code'])?></span></td>
                <td><span class="badge badge-teal"><?=htmlspecialchars($s['dept_code']??'N/A')?></span></td>
                <td>Sem <?=$s['semester']?></td>
                <td><?=$s['credits']?> credits</td>
                <td><?=$s['hours_per_week']?> hrs</td>
                <td><div style="display:flex;gap:6px">
                  <a href="manage_subjects.php?edit=<?=$s['id']?>" class="btn btn-outline btn-sm">✏️ Edit</a>
                  <a href="#" onclick="confirmDelete('manage_subjects.php?delete=<?=$s['id']?>','<?=htmlspecialchars($s['name'])?>')" class="btn btn-danger btn-sm">🗑️</a>
                </div></td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal-overlay <?=$edit?'open':''?>" id="subModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title"><?=$edit?'✏️ Edit Subject':'➕ Add Subject'?></div>
      <button class="modal-close" onclick="closeModal('subModal');window.history.replaceState({},'','manage_subjects.php')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="id" value="<?=$edit['id']??0?>">
      <div class="form-grid">
        <div class="form-group" style="grid-column:1/-1"><label>Subject Name *</label>
          <input type="text" name="name" class="form-control" required value="<?=htmlspecialchars($edit['name']??'')?>" placeholder="Data Structures & Algorithms">
        </div>
        <div class="form-group"><label>Subject Code *</label>
          <input type="text" name="code" class="form-control" required value="<?=htmlspecialchars($edit['code']??'')?>" placeholder="CS301">
        </div>
        <div class="form-group"><label>Department *</label>
          <select name="department_id" class="form-control" required>
            <option value="">Select</option>
            <?php foreach($departments as $d): ?>
            <option value="<?=$d['id']?>" <?=($edit['department_id']??'')==$d['id']?'selected':''?>><?=htmlspecialchars($d['name'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Semester *</label>
          <select name="semester" class="form-control" required>
            <?php for($s=1;$s<=8;$s++): ?>
            <option value="<?=$s?>" <?=($edit['semester']??'')==$s?'selected':''?>>Semester <?=$s?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="form-group"><label>Credits *</label>
          <input type="number" name="credits" class="form-control" min="1" max="6" value="<?=$edit['credits']??3?>" required>
        </div>
        <div class="form-group"><label>Hours/Week *</label>
          <input type="number" name="hours_per_week" class="form-control" min="1" max="10" value="<?=$edit['hours_per_week']??3?>" required>
        </div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px">
        <button type="button" onclick="closeModal('subModal')" class="btn btn-outline">Cancel</button>
        <button type="submit" class="btn btn-primary"><?=$edit?'💾 Update':'✅ Add Subject'?></button>
      </div>
    </form>
  </div>
</div>
<script src="js/app.js"></script>
<?php if($edit): ?><script>document.addEventListener('DOMContentLoaded',()=>openModal('subModal'));</script><?php endif; ?>
</body>
</html>
