<?php
require_once '../db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['conflicts' => []]);
    exit;
}

$faculty_id  = (int)($_POST['faculty_id']  ?? 0);
$classroom_id= (int)($_POST['classroom_id'] ?? 0);
$day         = sanitize($conn, $_POST['day']     ?? '');
$slot_id     = (int)($_POST['slot_id']     ?? 0);
$edit_id     = (int)($_POST['edit_id']     ?? 0);

$conflicts = [];

if ($faculty_id && $day && $slot_id) {
    $q = "SELECT s.name as subject, d.code, t.class_section
          FROM timetables t
          JOIN subjects s ON s.id=t.subject_id
          JOIN departments d ON d.id=t.department_id
          WHERE t.faculty_id=$faculty_id AND t.day_of_week='$day'
            AND t.time_slot_id=$slot_id AND t.id != $edit_id";
    $res = $conn->query($q);
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $conflicts[] = "Faculty already assigned to {$row['subject']} ({$row['code']}-{$row['class_section']}) at this slot.";
    }
}

if ($classroom_id && $day && $slot_id) {
    $q = "SELECT s.name as subject, d.code, t.class_section
          FROM timetables t
          JOIN subjects s ON s.id=t.subject_id
          JOIN departments d ON d.id=t.department_id
          WHERE t.classroom_id=$classroom_id AND t.day_of_week='$day'
            AND t.time_slot_id=$slot_id AND t.id != $edit_id";
    $res = $conn->query($q);
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $conflicts[] = "Room already booked for {$row['subject']} ({$row['code']}-{$row['class_section']}) at this slot.";
    }
}

echo json_encode(['conflicts' => $conflicts]);
