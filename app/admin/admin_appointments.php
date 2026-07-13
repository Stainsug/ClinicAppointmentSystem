<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/admin_auth.php';

if (empty($_SESSION['admin_appt_csrf_token'])) {
    $_SESSION['admin_appt_csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $appointmentId = (int)($_POST['appointment_id'] ?? 0);
    $newStatus = trim($_POST['new_status'] ?? '');

    if (!hash_equals($_SESSION['admin_appt_csrf_token'], $csrfToken)) {
        $errors[] = 'Invalid request token. Please refresh and try again.';
    }

    if ($appointmentId <= 0) {
        $errors[] = 'Invalid appointment ID.';
    }

    $allowedStatuses = ['Booked', 'Cancelled', 'Completed'];
    if (!in_array($newStatus, $allowedStatuses, true)) {
        $errors[] = 'Invalid appointment status selected.';
    }

    if (empty($errors)) {
        $updateSql = 'UPDATE Appointment SET status = ? WHERE appointment_id = ?';
        $updateStmt = mysqli_prepare($conn, $updateSql);

        if ($updateStmt === false) {
            $errors[] = 'Could not update appointment status right now.';
        } else {
            mysqli_stmt_bind_param($updateStmt, 'si', $newStatus, $appointmentId);
            mysqli_stmt_execute($updateStmt);
            $affected = mysqli_stmt_affected_rows($updateStmt);
            mysqli_stmt_close($updateStmt);

            if ($affected > 0) {
                $success = 'Appointment status updated.';
            } else {
                $errors[] = 'No appointment was updated. It may already have this status or not exist.';
            }
        }
    }
}

$filterStatus = trim($_GET['status'] ?? '');
$search = trim($_GET['q'] ?? '');
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) {
    $currentPage = 1;
}

$validStatuses = ['Booked', 'Cancelled', 'Completed'];
$hasStatusFilter = in_array($filterStatus, $validStatuses, true);

$perPage = 10;
$offset = ($currentPage - 1) * $perPage;

$whereParts = [];
$types = '';
$params = [];

if ($hasStatusFilter) {
    $whereParts[] = 'a.status = ?';
    $types .= 's';
    $params[] = $filterStatus;
}

if ($search !== '') {
    $whereParts[] = '(p.fullname LIKE ? OR p.email LIKE ? OR d.fullname LIKE ? OR d.specialization LIKE ?)';
    $searchLike = '%' . $search . '%';
    $types .= 'ssss';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}

$whereClause = '';
if (!empty($whereParts)) {
    $whereClause = ' WHERE ' . implode(' AND ', $whereParts);
}

$countSql = "SELECT COUNT(*) AS total
             FROM Appointment a
             INNER JOIN Patient p ON p.patient_id = a.patient_id
             INNER JOIN Schedule s ON s.schedule_id = a.schedule_id
             INNER JOIN Doctor d ON d.doctor_id = s.doctor_id" . $whereClause;

$totalRows = 0;
$countStmt = mysqli_prepare($conn, $countSql);
if ($countStmt) {
    if ($types !== '') {
        mysqli_stmt_bind_param($countStmt, $types, ...$params);
    }
    mysqli_stmt_execute($countStmt);
    $countResult = mysqli_stmt_get_result($countStmt);
    $countRow = mysqli_fetch_assoc($countResult);
    $totalRows = (int)($countRow['total'] ?? 0);
    mysqli_stmt_close($countStmt);
}

$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
    $offset = ($currentPage - 1) * $perPage;
}

$appointments = [];
$sql = "SELECT a.appointment_id, a.appointment_date, a.status,
               p.fullname AS patient_name, p.email AS patient_email,
               d.fullname AS doctor_name, d.specialization,
               s.available_date, s.available_time
        FROM Appointment a
        INNER JOIN Patient p ON p.patient_id = a.patient_id
        INNER JOIN Schedule s ON s.schedule_id = a.schedule_id
        INNER JOIN Doctor d ON d.doctor_id = s.doctor_id" . $whereClause .
        " ORDER BY a.appointment_date DESC, a.appointment_id DESC LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    $queryTypes = $types . 'ii';
    $queryParams = $params;
    $queryParams[] = $perPage;
    $queryParams[] = $offset;
    mysqli_stmt_bind_param($stmt, $queryTypes, ...$queryParams);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $appointments[] = $row;
    }
    mysqli_stmt_close($stmt);
}

$searchValue = htmlspecialchars($search, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Appointments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/refined-theme.css">
    <style>
        body {
            background:
                linear-gradient(135deg, rgba(243, 248, 255, 0.56), rgba(231, 248, 238, 0.56)),
                url('assets/images/admin-workspace-bg.svg') center/cover no-repeat fixed,
                linear-gradient(135deg, #f3f8ff, #e7f8ee);
            background-blend-mode: normal;
            min-height: 100vh;
        }
        .page-wrap { max-width: 1240px; margin: 30px auto; padding: 0 16px; }
        .panel { background: #fff; border-radius: 14px; box-shadow: 0 12px 30px rgba(0,0,0,0.08); padding: 24px; }
        .title-row { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:14px; flex-wrap:wrap; }
        .filter-row { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:12px; }
        .filter-form { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    </style>
</head>
<body>
    <div class="page-wrap">
        <div class="panel">
            <div class="title-row">
                <h2 class="mb-0">Admin - Manage Appointments</h2>
                <?php
                    $adminNavMode = 'buttons';
                    $adminActivePage = 'appointments';
                    require __DIR__ . '/../../includes/partials/admin_nav.php';
                ?>
            </div>

            <div class="section-hero">
                <p class="page-kicker">Operations</p>
                <h3 class="page-title"><span class="hero-chip">A</span>Appointment Control Center</h3>
                <p class="page-subtitle">Review patient bookings, update statuses, and monitor progress with filters.</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($success !== ''): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <div class="filter-row">
                <form method="GET" action="" class="filter-form">
                    <label for="status" class="form-label mb-0">Filter Status</label>
                    <select class="form-select" style="min-width: 180px;" id="status" name="status">
                        <option value="">All Statuses</option>
                        <option value="Booked" <?php echo $filterStatus === 'Booked' ? 'selected' : ''; ?>>Booked</option>
                        <option value="Cancelled" <?php echo $filterStatus === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        <option value="Completed" <?php echo $filterStatus === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                    <input type="text" class="form-control" style="min-width: 240px;" name="q" value="<?php echo $searchValue; ?>" placeholder="Search patient/doctor/specialization">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    <a href="admin_appointments.php" class="btn btn-outline-secondary">Reset</a>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th style="width:90px;">ID</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Specialization</th>
                            <th style="width:130px;">Date</th>
                            <th style="width:110px;">Time</th>
                            <th style="width:120px;">Status</th>
                            <th style="width:220px;">Update Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($appointments)): ?>
                            <?php foreach ($appointments as $appointment): ?>
                                <tr>
                                    <td><?php echo (int)$appointment['appointment_id']; ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($appointment['patient_name'], ENT_QUOTES, 'UTF-8'); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($appointment['patient_email'], ENT_QUOTES, 'UTF-8'); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($appointment['doctor_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($appointment['specialization'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($appointment['available_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(substr($appointment['available_time'], 0, 5), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <span class="badge <?php echo $appointment['status'] === 'Booked' ? 'text-bg-success' : ($appointment['status'] === 'Cancelled' ? 'text-bg-danger' : 'text-bg-primary'); ?>">
                                            <?php echo htmlspecialchars($appointment['status'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" action="" class="d-flex gap-2">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_appt_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="appointment_id" value="<?php echo (int)$appointment['appointment_id']; ?>">
                                            <select name="new_status" class="form-select form-select-sm" required>
                                                <option value="Booked" <?php echo $appointment['status'] === 'Booked' ? 'selected' : ''; ?>>Booked</option>
                                                <option value="Cancelled" <?php echo $appointment['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                <option value="Completed" <?php echo $appointment['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <div class="empty-icon">?</div>
                                        <h4>No appointments found</h4>
                                        <p>Try broadening your filters or clearing search to see more records.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="mt-3">
                    <ul class="pagination mb-0">
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <li class="page-item <?php echo $p === $currentPage ? 'active' : ''; ?>">
                                <a class="page-link" href="admin_appointments.php?page=<?php echo $p; ?>&status=<?php echo urlencode($filterStatus); ?>&q=<?php echo urlencode($search); ?>"><?php echo $p; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
