<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/doctor_auth.php';

$errors = [];
$success = '';

if (empty($_SESSION['doctor_schedule_csrf_token'])) {
    $_SESSION['doctor_schedule_csrf_token'] = bin2hex(random_bytes(32));
}

if (empty($_SESSION['doctor_logout_csrf_token'])) {
    $_SESSION['doctor_logout_csrf_token'] = bin2hex(random_bytes(32));
}

$doctor_id = (string)$loggedDoctorId;
$available_date = '';
$available_time = '';

if (isset($_GET['success'])) {
    if ($_GET['success'] === 'added') {
        $success = 'Availability slot added successfully.';
    } elseif ($_GET['success'] === 'updated') {
        $success = 'Availability slot updated successfully.';
    } elseif ($_GET['success'] === 'deleted') {
        $success = 'Availability slot deleted successfully.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $doctor_id = (string)$loggedDoctorId;
    $available_date = trim($_POST['available_date'] ?? '');
    $available_time = trim($_POST['available_time'] ?? '');

    if (!hash_equals($_SESSION['doctor_schedule_csrf_token'], $csrfToken)) {
        $errors[] = 'Invalid request token. Please refresh and try again.';
    }

    if ($available_date === '') {
        $errors[] = 'Available date is required.';
    }

    if ($available_time === '') {
        $errors[] = 'Available time is required.';
    }

    if (empty($errors)) {
        $checkDoctorSql = 'SELECT doctor_id FROM Doctor WHERE doctor_id = ? LIMIT 1';
        $checkDoctorStmt = mysqli_prepare($conn, $checkDoctorSql);

        if ($checkDoctorStmt === false) {
            $errors[] = 'Unable to validate doctor right now.';
        } else {
            $doctorIdInt = (int)$doctor_id;
            mysqli_stmt_bind_param($checkDoctorStmt, 'i', $doctorIdInt);
            mysqli_stmt_execute($checkDoctorStmt);
            mysqli_stmt_store_result($checkDoctorStmt);

            if (mysqli_stmt_num_rows($checkDoctorStmt) === 0) {
                $errors[] = 'Selected doctor does not exist.';
            }

            mysqli_stmt_close($checkDoctorStmt);
        }
    }

    if (empty($errors)) {
        $insertSql = 'INSERT INTO Schedule (doctor_id, available_date, available_time) VALUES (?, ?, ?)';
        $insertStmt = mysqli_prepare($conn, $insertSql);

        if ($insertStmt === false) {
            $errors[] = 'Unable to add availability right now.';
        } else {
            $doctorIdInt = (int)$doctor_id;
            mysqli_stmt_bind_param($insertStmt, 'iss', $doctorIdInt, $available_date, $available_time);

            if (mysqli_stmt_execute($insertStmt)) {
                mysqli_stmt_close($insertStmt);
                header('Location: schedule_manage.php?success=added');
                exit;
            }

            $dbErrorCode = mysqli_errno($conn);
            mysqli_stmt_close($insertStmt);

            if ($dbErrorCode === 1062) {
                $errors[] = 'This doctor already has this date/time slot.';
            } else {
                $errors[] = 'Failed to add availability. Please try again.';
            }
        }
    }
}

$doctorName = $_SESSION['doctor_name'] ?? '';
$doctorSpecialization = '';

$doctorSql = 'SELECT fullname, specialization FROM Doctor WHERE doctor_id = ? LIMIT 1';
$doctorStmt = mysqli_prepare($conn, $doctorSql);

if ($doctorStmt) {
    mysqli_stmt_bind_param($doctorStmt, 'i', $loggedDoctorId);
    mysqli_stmt_execute($doctorStmt);
    $doctorResult = mysqli_stmt_get_result($doctorStmt);
    $doctorRow = mysqli_fetch_assoc($doctorResult);
    mysqli_stmt_close($doctorStmt);

    if ($doctorRow) {
        $doctorName = $doctorRow['fullname'];
        $doctorSpecialization = $doctorRow['specialization'];
    }
}

$schedules = [];
$scheduleSql = 'SELECT s.schedule_id, s.available_date, s.available_time, d.doctor_id, d.fullname, d.specialization
                FROM Schedule s
                INNER JOIN Doctor d ON d.doctor_id = s.doctor_id
                WHERE s.doctor_id = ?
                ORDER BY s.available_date ASC, s.available_time ASC';

$scheduleStmt = mysqli_prepare($conn, $scheduleSql);

if ($scheduleStmt) {
    mysqli_stmt_bind_param($scheduleStmt, 'i', $loggedDoctorId);
    mysqli_stmt_execute($scheduleStmt);
    $scheduleResult = mysqli_stmt_get_result($scheduleStmt);

    if ($scheduleResult) {
        while ($row = mysqli_fetch_assoc($scheduleResult)) {
            $schedules[] = $row;
        }
    }

    mysqli_stmt_close($scheduleStmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Availability</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/refined-theme.css">
    <style>
        body {
            background:
                linear-gradient(140deg, rgba(244, 248, 255, 0.56), rgba(232, 247, 238, 0.56)),
                url('assets/images/doctor-workspace-bg.svg') center/cover no-repeat fixed,
                linear-gradient(140deg, #f4f8ff, #e8f7ee);
            background-blend-mode: normal;
            min-height: 100vh;
        }

        .page-wrap {
            max-width: 1180px;
            margin: 30px auto;
            padding: 0 16px;
        }

        .panel {
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.1);
            padding: 24px;
        }

        .header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }
    </style>
</head>
<body>
    <div class="page-wrap">
        <div class="panel mb-4">
            <div class="header-row">
                <h2 class="mb-0">Doctor Availability Scheduling</h2>
                <div class="d-flex gap-2">
                    <a href="dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
                    <form method="POST" action="doctor_login.php" class="m-0">
                        <input type="hidden" name="action" value="logout">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['doctor_logout_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" class="btn btn-outline-danger">Doctor Logout</button>
                    </form>
                </div>
            </div>

            <div class="section-hero">
                <p class="page-kicker">Scheduling</p>
                <h3 class="page-title">Publish Your Availability</h3>
                <p class="page-subtitle">Add precise date and time slots so patients can discover and book you quickly.</p>
            </div>

            <?php if ($success !== ''): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['doctor_schedule_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <div class="col-md-4">
                    <label class="form-label">Doctor</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars(trim($doctorName . ' - ' . $doctorSpecialization, ' -'), ENT_QUOTES, 'UTF-8'); ?>" readonly>
                    <input type="hidden" name="doctor_id" value="<?php echo (int)$loggedDoctorId; ?>">
                </div>

                <div class="col-md-3">
                    <label for="available_date" class="form-label">Available Date</label>
                    <input type="date" class="form-control" id="available_date" name="available_date" value="<?php echo htmlspecialchars($available_date, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="col-md-3">
                    <label for="available_time" class="form-label">Available Time</label>
                    <input type="time" class="form-control" id="available_time" name="available_time" value="<?php echo htmlspecialchars($available_time, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="col-md-2 d-grid align-self-end">
                    <button type="submit" class="btn btn-success">Add Slot</button>
                </div>
            </form>
        </div>

        <div class="panel">
            <h4 class="mb-2">Availability Slots</h4>
            <p class="page-subtitle" style="margin-bottom: 12px;">Keep this list current to avoid missed bookings.</p>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 90px;">ID</th>
                            <th>Doctor</th>
                            <th>Specialization</th>
                            <th style="width: 180px;">Date</th>
                            <th style="width: 150px;">Time</th>
                            <th style="width: 170px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($schedules)): ?>
                            <?php foreach ($schedules as $schedule): ?>
                                <tr>
                                    <td><?php echo (int)$schedule['schedule_id']; ?></td>
                                    <td><?php echo htmlspecialchars($schedule['fullname'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($schedule['specialization'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($schedule['available_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(substr($schedule['available_time'], 0, 5), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <a class="btn btn-sm btn-primary" href="schedule_edit.php?id=<?php echo (int)$schedule['schedule_id']; ?>">Edit</a>
                                        <form method="POST" action="schedule_delete.php" class="d-inline m-0" onsubmit="return confirm('Delete this availability slot?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['doctor_schedule_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="id" value="<?php echo (int)$schedule['schedule_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <div class="empty-icon">+</div>
                                        <h4>No availability slots yet</h4>
                                        <p>Add your first slot above so patients can start booking you.</p>
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
