<?php
// includes/sidebar.php — shared sidebar component
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon">📅</div>
    <h2>Smart Timetable</h2>
    <span>Schedule Management System</span>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Main</div>

    <a href="dashboard.php" class="nav-item <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
      <span class="nav-icon">📊</span> Dashboard
    </a>

    <a href="view_schedule.php" class="nav-item <?= $currentPage === 'view_schedule.php' ? 'active' : '' ?>">
      <span class="nav-icon">📋</span> View Schedule
    </a>

    <a href="create_timetable.php" class="nav-item <?= $currentPage === 'create_timetable.php' ? 'active' : '' ?>">
      <span class="nav-icon">➕</span> Create Timetable
    </a>

    <div class="nav-section-label" style="margin-top:8px">Management</div>

    <a href="faculty_allocation.php" class="nav-item <?= $currentPage === 'faculty_allocation.php' ? 'active' : '' ?>">
      <span class="nav-icon">👨‍🏫</span> Faculty Allocation
    </a>

    <a href="manage_faculty.php" class="nav-item <?= $currentPage === 'manage_faculty.php' ? 'active' : '' ?>">
      <span class="nav-icon">👥</span> Manage Faculty
    </a>

    <a href="manage_subjects.php" class="nav-item <?= $currentPage === 'manage_subjects.php' ? 'active' : '' ?>">
      <span class="nav-icon">📚</span> Manage Subjects
    </a>

    <a href="manage_classrooms.php" class="nav-item <?= $currentPage === 'manage_classrooms.php' ? 'active' : '' ?>">
      <span class="nav-icon">🏫</span> Manage Classrooms
    </a>

  </nav>

  <div class="sidebar-footer">
    <div class="user-card">
      <div class="user-avatar">
        <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?>
      </div>
      <div class="user-info">
        <div class="user-name"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></div>
        <div class="user-role"><?= ucfirst($_SESSION['role'] ?? 'admin') ?></div>
      </div>
      <a href="logout.php" class="logout-btn" title="Logout">🚪</a>
    </div>
  </div>
</aside>
