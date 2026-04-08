<?php
require_once 'db.php';
requireLogin();
$flash = getFlash();

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM classrooms WHERE id=$id");
    setFlash('success','✅ Classroom deleted.'); header('Location: manage_classrooms.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id   = (int)($_POST['id']??0);
    $room = sanitize($conn,$_POST['room_number']);
    $bld  = sanitize($conn,$_POST['building']);
    $cap  = (int)$_POST['capacity'];
    $type = sanitize($conn,$_POST['room_type']);
    if ($id) {
        $sql = "UPDATE classrooms SET room_number='$room',building='$bld',capacity=$cap,room_type='$type' WHERE id=$id";
        $msg = '✅ Classroom updated!';
    } else {
        $sql = "INSERT INTO classrooms (room_number,building,capacity,room_type) VALUES ('$room','$bld',$cap,'$type')";
        $msg = '✅ Classroom added!';
    }
    if ($conn->query($sql)) setFlash('success',$msg); else setFlash('error','❌ '.$conn->error);
    header('Location: manage_classrooms.php'); exit;
}
$classrooms = $conn->query("SELECT * FROM classrooms ORDER BY building,room_number")->fetch_all(MYSQLI_ASSOC);
$edit = null;
if (isset($_GET['edit'])) { $r=$conn->query("SELECT * FROM classrooms WHERE id=".(int)$_GET['edit']); $edit=$r?$r->fetch_assoc():null; }
$typeColors = ['lecture'=>'badge-purple','lab'=>'badge-teal','seminar'=>'badge-orange','auditorium'=>'badge-pink'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Classrooms — Smart Timetable System</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="app-layout">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main-content">
    <div class="topbar">
      <div><div class="topbar-title">🏫 Manage Classrooms</div><div class="topbar-subtitle">Add, edit or remove classrooms and labs</div></div>
      <div class="topbar-actions"><button onclick="openModal('roomModal')" class="btn btn-primary btn-sm">+ Add Room</button></div>
    </div>
    <div class="page-content">
      <?php if ($flash): ?>
      <div class="alert alert-<?=$flash['type']?>"><span class="alert-icon"><?=$flash['type']==='success'?'✅':'❌'?></span><?=htmlspecialchars($flash['msg'])?></div>
      <?php endif; ?>
      <div class="filter-bar">
        <div class="search-input-wrap"><span class="search-icon">🔍</span>
        <input type="text" id="table-search" class="form-control search-input" placeholder="Search rooms..."></div>
      </div>
      <div class="card">
        <div class="table-wrapper">
          <table class="data-table">
            <thead><tr><th>#</th><th>Room No.</th><th>Building</th><th>Capacity</th><th>Type</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if(empty($classrooms)): ?><tr><td colspan="6"><div class="empty-state"><div class="empty-icon">🏫</div><h3>No classrooms added yet</h3></div></td></tr>
              <?php else: foreach($classrooms as $i=>$c): ?>
              <tr>
                <td style="color:var(--text-muted)"><?=$i+1?></td>
                <td style="font-weight:700;font-size:15px;color:var(--accent-light)"><?=htmlspecialchars($c['room_number'])?></td>
                <td><?=htmlspecialchars($c['building']??'—')?></td>
                <td><span class="badge badge-teal">👥 <?=$c['capacity']?> seats</span></td>
                <td><span class="badge <?=$typeColors[$c['room_type']]??'badge-purple'?>"><?=ucfirst($c['room_type'])?></span></td>
                <td><div style="display:flex;gap:6px">
                  <a href="manage_classrooms.php?edit=<?=$c['id']?>" class="btn btn-outline btn-sm">✏️ Edit</a>
                  <a href="#" onclick="confirmDelete('manage_classrooms.php?delete=<?=$c['id']?>','Room <?=htmlspecialchars($c['room_number'])?>')" class="btn btn-danger btn-sm">🗑️</a>
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

<div class="modal-overlay <?=$edit?'open':''?>" id="roomModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title"><?=$edit?'✏️ Edit Classroom':'➕ Add Classroom'?></div>
      <button class="modal-close" onclick="closeModal('roomModal');window.history.replaceState({},'','manage_classrooms.php')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="id" value="<?=$edit['id']??0?>">
      <div class="form-grid">
        <div class="form-group"><label>Room Number *</label>
          <input type="text" name="room_number" class="form-control" required value="<?=htmlspecialchars($edit['room_number']??'')?>" placeholder="101 or Lab-1">
        </div>
        <div class="form-group"><label>Building</label>
          <input type="text" name="building" class="form-control" value="<?=htmlspecialchars($edit['building']??'')?>" placeholder="Block A">
        </div>
        <div class="form-group"><label>Capacity *</label>
          <input type="number" name="capacity" class="form-control" required min="1" max="500" value="<?=$edit['capacity']??30?>">
        </div>
        <div class="form-group"><label>Room Type *</label>
          <select name="room_type" class="form-control" required>
            <?php foreach(['lecture','lab','seminar','auditorium'] as $t): ?>
            <option value="<?=$t?>" <?=($edit['room_type']??'')===$t?'selected':''?>><?=ucfirst($t)?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px">
        <button type="button" onclick="closeModal('roomModal')" class="btn btn-outline">Cancel</button>
        <button type="submit" class="btn btn-primary"><?=$edit?'💾 Update':'✅ Add Room'?></button>
      </div>
    </form>
  </div>
</div>
<script src="js/app.js"></script>
<?php if($edit): ?><script>document.addEventListener('DOMContentLoaded',()=>openModal('roomModal'));</script><?php endif; ?>
</body>
</html>
