<?php
require_once 'db.php';
requireLogin();
$flash = getFlash();

// Handle DELETE
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM faculty WHERE id=$id");
    setFlash('success', '✅ Faculty member deleted.');
    header('Location: manage_faculty.php'); exit;
}

// Handle ADD / EDIT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id   = (int)($_POST['id'] ?? 0);
    $name = sanitize($conn, $_POST['name']);
    $email= sanitize($conn, $_POST['email']);
    $phone= sanitize($conn, $_POST['phone']);
    $dept = (int)$_POST['department_id'];
    $spec = sanitize($conn, $_POST['specialization']);
    $maxh = (int)$_POST['max_hours_per_week'];

    if ($id) {
        $sql = "UPDATE faculty SET name='$name',email='$email',phone='$phone',
                department_id=$dept,specialization='$spec',max_hours_per_week=$maxh WHERE id=$id";
        $msg = '✅ Faculty updated successfully!';
    } else {
        $sql = "INSERT INTO faculty (name,email,phone,department_id,specialization,max_hours_per_week)
                VALUES ('$name','$email','$phone',$dept,'$spec',$maxh)";
        $msg = '✅ Faculty member added successfully!';
    }
    if ($conn->query($sql)) { setFlash('success',$msg); }
    else { setFlash('error','❌ Error: '.$conn->error); }
    header('Location: manage_faculty.php'); exit;
}

$departments = $conn->query("SELECT * FROM departments ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$faculty_list = $conn->query("
    SELECT f.*, d.name as dept_name, d.code as dept_code
    FROM faculty f LEFT JOIN departments d ON d.id=f.department_id
    ORDER BY f.name
")->fetch_all(MYSQLI_ASSOC);

// Edit prefill
$edit = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $r = $conn->query("SELECT * FROM faculty WHERE id=$eid");
    $edit = $r ? $r->fetch_assoc() : null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Faculty — Smart Timetable System</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="app-layout">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main-content">
    <div class="topbar">
      <div>
        <div class="topbar-title">👥 Manage Faculty</div>
        <div class="topbar-subtitle">Add, edit or remove faculty members</div>
      </div>
      <div class="topbar-actions">
        <button onclick="openModal('facultyModal')" class="btn btn-primary btn-sm">+ Add Faculty</button>
      </div>
    </div>
    <div class="page-content">
      <?php if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] ?>">
        <span class="alert-icon"><?= $flash['type']==='success'?'✅':'❌'?></span>
        <?= htmlspecialchars($flash['msg']) ?>
      </div>
      <?php endif; ?>

      <div class="filter-bar">
        <div class="search-input-wrap">
          <span class="search-icon">🔍</span>
          <input type="text" id="table-search" class="form-control search-input" placeholder="Search faculty...">
        </div>
      </div>

      <div class="card">
        <div class="table-wrapper">
          <table class="data-table">
            <thead>
              <tr><th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Department</th><th>Specialization</th><th>Max Hrs/Wk</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php if (empty($faculty_list)): ?>
              <tr><td colspan="8"><div class="empty-state"><div class="empty-icon">👥</div><h3>No faculty added yet</h3></div></td></tr>
              <?php else: ?>
              <?php foreach ($faculty_list as $i => $f): ?>
              <tr>
                <td style="color:var(--text-muted)"><?= $i+1 ?></td>
                <td>
                  <div style="display:flex;align-items:center;gap:10px">
                    <div style="width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,var(--accent),var(--pink));display:flex;align-items:center;justify-content:center;font-weight:700;color:white;font-size:13px">
                      <?= strtoupper(substr($f['name'],0,1)) ?>
                    </div>
                    <span style="font-weight:600"><?= htmlspecialchars($f['name']) ?></span>
                  </div>
                </td>
                <td style="color:var(--text-secondary)"><?= htmlspecialchars($f['email']) ?></td>
                <td><?= htmlspecialchars($f['phone'] ?? '—') ?></td>
                <td><span class="badge badge-purple"><?= htmlspecialchars($f['dept_code'] ?? 'N/A') ?></span></td>
                <td style="font-size:13px;color:var(--text-secondary)"><?= htmlspecialchars($f['specialization'] ?? '—') ?></td>
                <td><span class="badge badge-teal"><?= $f['max_hours_per_week'] ?> hrs</span></td>
                <td>
                  <div style="display:flex;gap:6px">
                    <a href="manage_faculty.php?edit=<?=$f['id']?>" class="btn btn-outline btn-sm">✏️ Edit</a>
                    <a href="#" onclick="confirmDelete('manage_faculty.php?delete=<?=$f['id']?>','<?= htmlspecialchars($f['name']) ?>')" class="btn btn-danger btn-sm">🗑️</a>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal-overlay <?= $edit ? 'open' : '' ?>" id="facultyModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title"><?= $edit ? '✏️ Edit Faculty' : '➕ Add Faculty' ?></div>
      <button class="modal-close" onclick="closeModal('facultyModal');window.history.replaceState({},'','manage_faculty.php')">✕</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
      <div class="form-grid">
        <div class="form-group">
          <label>Full Name *</label>
          <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($edit['name']??'') ?>" placeholder="Dr. John Doe">
        </div>
        <div class="form-group">
          <label>Email *</label>
          <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($edit['email']??'') ?>" placeholder="john@college.edu">
        </div>
        <div class="form-group">
          <label>Phone</label>
          <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($edit['phone']??'') ?>" placeholder="9876543210">
        </div>
        <div class="form-group">
          <label>Department *</label>
          <select name="department_id" class="form-control" required>
            <option value="">Select Department</option>
            <?php foreach ($departments as $d): ?>
            <option value="<?=$d['id']?>" <?= ($edit['department_id']??'')==$d['id']?'selected':'' ?>><?= htmlspecialchars($d['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="grid-column:1/-1">
          <label>Specialization</label>
          <input type="text" name="specialization" class="form-control" value="<?= htmlspecialchars($edit['specialization']??'') ?>" placeholder="e.g. Data Structures & Algorithms">
        </div>
        <div class="form-group">
          <label>Max Hours/Week *</label>
          <input type="number" name="max_hours_per_week" class="form-control" required min="1" max="40" value="<?= $edit['max_hours_per_week']??20 ?>">
        </div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px">
        <button type="button" onclick="closeModal('facultyModal')" class="btn btn-outline">Cancel</button>
        <button type="submit" class="btn btn-primary"><?= $edit ? '💾 Update' : '✅ Add Faculty' ?></button>
      </div>
    </form>
  </div>
</div>

<script src="js/app.js"></script>
<?php if ($edit): ?>
<script>document.addEventListener('DOMContentLoaded',()=>openModal('facultyModal'));</script>
<?php endif; ?>
</body>
</html>
