<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/admin_auth.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: admin_doctors.php?error=1');
    exit;
}

$deleteSql = 'DELETE FROM Doctor WHERE doctor_id = ?';
$deleteStmt = mysqli_prepare($conn, $deleteSql);

if ($deleteStmt === false) {
    header('Location: admin_doctors.php?error=1');
    exit;
}

mysqli_stmt_bind_param($deleteStmt, 'i', $id);
$ok = mysqli_stmt_execute($deleteStmt);
mysqli_stmt_close($deleteStmt);

if ($ok) {
    header('Location: admin_doctors.php?success=deleted');
    exit;
}

header('Location: admin_doctors.php?error=1');
exit;
