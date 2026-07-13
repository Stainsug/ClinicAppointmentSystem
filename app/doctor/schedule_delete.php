<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/doctor_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: schedule_manage.php');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['doctor_schedule_csrf_token']) || !hash_equals($_SESSION['doctor_schedule_csrf_token'], $csrfToken)) {
    header('Location: schedule_manage.php');
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    header('Location: schedule_manage.php');
    exit;
}

$deleteSql = 'DELETE FROM Schedule WHERE schedule_id = ? AND doctor_id = ?';
$deleteStmt = mysqli_prepare($conn, $deleteSql);

if ($deleteStmt === false) {
    header('Location: schedule_manage.php');
    exit;
}

mysqli_stmt_bind_param($deleteStmt, 'ii', $id, $loggedDoctorId);
mysqli_stmt_execute($deleteStmt);
mysqli_stmt_close($deleteStmt);

header('Location: schedule_manage.php?success=deleted');
exit;
