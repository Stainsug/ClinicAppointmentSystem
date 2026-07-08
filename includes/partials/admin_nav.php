<?php
$adminNavMode = $adminNavMode ?? 'buttons';
$adminActivePage = $adminActivePage ?? '';

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
    echo '<a href="admin_login.php?logout=1">Logout</a>';
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
echo '<a class="btn btn-outline-danger" href="admin_login.php?logout=1">Logout</a>';
echo '</div>';
