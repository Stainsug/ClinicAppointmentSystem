<?php
require_once __DIR__ . '/../../config.php';

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

if (empty($_SESSION['is_patient_logged_in']) || empty($_SESSION['patient_id'])) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['my_appt_csrf_token'])) {
    $_SESSION['my_appt_csrf_token'] = bin2hex(random_bytes(32));
}

$patientId = (int)$_SESSION['patient_id'];
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $appointmentId = (int)($_POST['appointment_id'] ?? 0);

    if (!hash_equals($_SESSION['my_appt_csrf_token'], $csrfToken)) {
        $errors[] = 'Invalid request token. Please refresh and try again.';
    }

    if ($appointmentId <= 0) {
        $errors[] = 'Invalid appointment selected.';
    }

    if (empty($errors)) {
        $sql = "UPDATE Appointment a
                INNER JOIN Schedule s ON s.schedule_id = a.schedule_id
                SET a.status = 'Cancelled'
                WHERE a.appointment_id = ?
                  AND a.patient_id = ?
                  AND a.status = 'Booked'";

        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt === false) {
            $errors[] = 'Unable to cancel appointment right now.';
        } else {
            mysqli_stmt_bind_param($stmt, 'ii', $appointmentId, $patientId);
            mysqli_stmt_execute($stmt);
            $affected = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);

            if ($affected === 1) {
                $success = 'Appointment cancelled successfully.';
            } else {
                $errors[] = 'This appointment could not be cancelled.';
            }
        }
    }
}

$appointments = [];
$listSql = "SELECT a.appointment_id, a.appointment_date, a.status,
                   d.fullname AS doctor_name, d.specialization,
                   s.available_date, s.available_time
            FROM Appointment a
            INNER JOIN Schedule s ON s.schedule_id = a.schedule_id
            INNER JOIN Doctor d ON d.doctor_id = s.doctor_id
            WHERE a.patient_id = ?
            ORDER BY s.available_date DESC, s.available_time DESC";

$listStmt = mysqli_prepare($conn, $listSql);
if ($listStmt) {
    mysqli_stmt_bind_param($listStmt, 'i', $patientId);
    mysqli_stmt_execute($listStmt);
    $result = mysqli_stmt_get_result($listStmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $appointments[] = $row;
    }

    mysqli_stmt_close($listStmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/refined-theme.css">
    <style>
        body {
            background:
                linear-gradient(135deg, rgba(242, 248, 255, 0.56), rgba(232, 248, 239, 0.56)),
                url('assets/images/patient-workspace-bg.svg') center/cover no-repeat fixed,
                linear-gradient(135deg, #f2f8ff, #e8f8ef);
            background-blend-mode: normal;
            min-height: 100vh;
        }
        .page-wrap { max-width: 1160px; margin: 30px auto; padding: 0 16px; }
        .panel { background:#fff; border-radius:14px; box-shadow: 0 12px 30px rgba(0,0,0,0.08); padding:24px; }
        .title-row { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:14px; }
    </style>
</head>
<body>
    <div class="page-wrap">
        <div class="panel">
            <div class="title-row">
                <h2 class="mb-0">My Appointments</h2>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="dashboard.php" class="btn btn-outline-secondary">Dashboard</a>
                    <a href="book_appointments.php" class="btn btn-outline-primary">Book Appointment</a>
                </div>
            </div>

            <div class="section-hero">
                <p class="page-kicker">Visits</p>
                <h3 class="page-title"><span class="hero-chip">M</span>Appointment Timeline</h3>
                <p class="page-subtitle">Track your upcoming bookings and cancel when plans change.</p>
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

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th style="width:90px;">ID</th>
                            <th>Doctor</th>
                            <th>Specialization</th>
                            <th style="width:130px;">Date</th>
                            <th style="width:110px;">Time</th>
                            <th style="width:120px;">Status</th>
                            <th style="width:140px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($appointments)): ?>
                            <?php foreach ($appointments as $appointment): ?>
                                <?php $canCancel = $appointment['status'] === 'Booked'; ?>
                                <tr>
                                    <td><?php echo (int)$appointment['appointment_id']; ?></td>
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
                                        <?php if ($canCancel): ?>
                                            <form method="POST" action="" class="m-0">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['my_appt_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="appointment_id" value="<?php echo (int)$appointment['appointment_id']; ?>">
                                                <button class="btn btn-sm btn-outline-danger" type="submit" onclick="return confirm('Cancel this appointment?');">Cancel</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <div class="empty-icon">B</div>
                                        <h4>No appointments found</h4>
                                        <p>Start by booking your first visit from the booking page.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
