<?php
require_once 'db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($conn, $_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['faculty_id'] = $user['faculty_id'];
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password. Please try again.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — Smart Timetable System</title>
  <meta name="description" content="Login to Smart Timetable & Schedule Management System">
  <link rel="stylesheet" href="css/style.css">
  <style>
    .login-divider { display:flex;align-items:center;gap:12px;margin:20px 0;color:var(--text-muted);font-size:12px; }
    .login-divider::before,.login-divider::after { content:'';flex:1;height:1px;background:var(--border-light); }
    .demo-cred { background:rgba(108,99,255,0.08);border:1px solid var(--border);border-radius:8px;padding:12px 16px;margin-top:16px;font-size:12px;color:var(--text-secondary); }
    .demo-cred strong { color:var(--accent-light); }
    .input-group { position:relative; }
    .input-icon { position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:16px;pointer-events:none; }
    .input-group .form-control { padding-left:42px; }
    .toggle-pw { position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:16px;transition:var(--transition); }
    .toggle-pw:hover { color:var(--accent-light); }
  </style>
</head>
<body>
<div class="login-wrap">
  <div class="login-bg-blob blob-1"></div>
  <div class="login-bg-blob blob-2"></div>

  <div class="login-card fade-in">
    <div class="login-logo">
      <div class="logo-box">📅</div>
      <h1>Smart Timetable</h1>
      <p>Schedule Management System</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error">
      <span class="alert-icon">❌</span>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form class="login-form" method="POST" action="">
      <div class="form-group">
        <label for="username">Username</label>
        <div class="input-group">
          <span class="input-icon">👤</span>
          <input type="text" id="username" name="username" class="form-control"
                 placeholder="Enter your username" required autocomplete="username"
                 value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <div class="input-group">
          <span class="input-icon">🔒</span>
          <input type="password" id="password" name="password" class="form-control"
                 placeholder="Enter your password" required autocomplete="current-password">
          <button type="button" class="toggle-pw" onclick="togglePassword()" id="pw-toggle">👁️</button>
        </div>
      </div>

      <button type="submit" class="login-btn">Sign In →</button>
    </form>

    <div class="login-divider">Demo Credentials</div>
    <div class="demo-cred">
      🔑 Username: <strong>admin</strong> &nbsp;|&nbsp; Password: <strong>admin123</strong>
    </div>

    <p class="login-hint">Smart Timetable v1.0 &nbsp;·&nbsp; <span>Academic Year 2025–2026</span></p>
  </div>
</div>

<script>
function togglePassword() {
  const pw = document.getElementById('password');
  const btn = document.getElementById('pw-toggle');
  if (pw.type === 'password') {
    pw.type = 'text'; btn.textContent = '🙈';
  } else {
    pw.type = 'password'; btn.textContent = '👁️';
  }
}
// Auto-remove error alert
const alert = document.querySelector('.alert');
if (alert) setTimeout(() => { alert.style.opacity='0'; alert.style.transition='0.4s'; setTimeout(()=>alert.remove(),400); }, 4000);
</script>
</body>
</html>
