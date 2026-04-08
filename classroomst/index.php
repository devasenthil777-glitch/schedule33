<?php
// index.php — Entry point: redirect to dashboard if logged in, else login
require_once 'db.php';
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
