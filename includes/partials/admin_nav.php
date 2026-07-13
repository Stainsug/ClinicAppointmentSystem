<?php
$adminNavMode = $adminNavMode ?? 'buttons';
$adminActivePage = $adminActivePage ?? '';

if (session_status() === PHP_SESSION_ACTIVE && empty($_SESSION['admin_logout_csrf_token'])) {
    $_SESSION['admin_logout_csrf_token'] = bin2hex(random_bytes(32));
}

$items = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'admin_dashboard.php'],
    ['key' => 'doctors', 'label' => 'Doctors', 'href' => 'admin_doctors.php'],
    ['key' => 'appointments', 'label' => 'Appointments', 'href' => 'admin_appointments.php'],
    ['key' => 'reports', 'label' => 'Reports', 'href' => 'admin_reports.php'],
    ['key' => 'password', 'label' => 'Change Password', 'href' => 'admin_change_password.php'],
];

if ($adminNavMode === 'menu') {
    echo '<nav class="menu">';
    foreach ($items as $item) {
        $activeClass = $adminActivePage === $item['key'] ? 'active' : '';
        echo '<a class="' . $activeClass . '" href="' . htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') . '</a>';
    }
    echo '<form method="POST" action="admin_login.php" class="d-inline m-0">';
    echo '<input type="hidden" name="action" value="logout">';
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['admin_logout_csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') . '">';
    echo '<button type="submit" class="menu-logout">Logout</button>';
    echo '</form>';
    echo '</nav>';
    return;
}

echo '<div class="d-flex gap-2 flex-wrap">';
foreach ($items as $item) {
    $btnClass = $adminActivePage === $item['key'] ? 'btn btn-primary' : 'btn btn-outline-secondary';

    if ($item['key'] === 'password' && $adminActivePage !== $item['key']) {
        $btnClass = 'btn btn-outline-info';
    }

    echo '<a class="' . $btnClass . '" href="' . htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') . '</a>';
}
echo '<form method="POST" action="admin_login.php" class="d-inline m-0">';
echo '<input type="hidden" name="action" value="logout">';
echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['admin_logout_csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') . '">';
echo '<button type="submit" class="btn btn-outline-danger">Logout</button>';
echo '</form>';
echo '</div>';
