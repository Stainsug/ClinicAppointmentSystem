<?php
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$target = 'admin_doctor_edit.php';

if ($id > 0) {
    $target .= '?id=' . $id;
}

header('Location: ' . $target);
exit;
