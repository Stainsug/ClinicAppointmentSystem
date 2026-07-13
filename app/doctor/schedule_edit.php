<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/doctor_auth.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: schedule_manage.php');
    exit;
}

$errors = [];

$selectSql = 'SELECT schedule_id, doctor_id, available_date, available_time FROM Schedule WHERE schedule_id = ? AND doctor_id = ? LIMIT 1';
$selectStmt = mysqli_prepare($conn, $selectSql);

if ($selectStmt === false) {
    header('Location: schedule_manage.php');
    exit;
}

mysqli_stmt_bind_param($selectStmt, 'ii', $id, $loggedDoctorId);
mysqli_stmt_execute($selectStmt);
$selectResult = mysqli_stmt_get_result($selectStmt);
$schedule = mysqli_fetch_assoc($selectResult);
mysqli_stmt_close($selectStmt);

if (!$schedule) {
    header('Location: schedule_manage.php');
    exit;
}

$doctor_id = (string)$loggedDoctorId;
$available_date = $schedule['available_date'];
$available_time = substr($schedule['available_time'], 0, 5);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctor_id = (string)$loggedDoctorId;
    $available_date = trim($_POST['available_date'] ?? '');
    $available_time = trim($_POST['available_time'] ?? '');

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
        $updateSql = 'UPDATE Schedule SET doctor_id = ?, available_date = ?, available_time = ? WHERE schedule_id = ?';
        $updateStmt = mysqli_prepare($conn, $updateSql);

        if ($updateStmt === false) {
            $errors[] = 'Unable to update availability right now.';
        } else {
            $doctorIdInt = (int)$doctor_id;
            mysqli_stmt_bind_param($updateStmt, 'issi', $doctorIdInt, $available_date, $available_time, $id);

            if (mysqli_stmt_execute($updateStmt)) {
                mysqli_stmt_close($updateStmt);
                header('Location: schedule_manage.php?success=updated');
                exit;
            }

            $dbErrorCode = mysqli_errno($conn);
            mysqli_stmt_close($updateStmt);

            if ($dbErrorCode === 1062) {
                $errors[] = 'This doctor already has this date/time slot.';
            } else {
                $errors[] = 'Failed to update availability. Please try again.';
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Availability</title>
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
            max-width: 760px;
            margin: 34px auto;
            padding: 0 16px;
        }

        .panel {
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.1);
            padding: 24px;
        }
    </style>
</head>
<body>
    <div class="page-wrap">
        <div class="panel">
            <div class="section-hero">
                <p class="page-kicker">Scheduling</p>
                <h3 class="page-title">Edit Availability Slot</h3>
                <p class="page-subtitle">Adjust the date or time to keep your calendar accurate for patients.</p>
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

            <form method="POST" action="" class="row g-3">
                <div class="col-12">
                    <label class="form-label">Doctor</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars(trim($doctorName . ' - ' . $doctorSpecialization, ' -'), ENT_QUOTES, 'UTF-8'); ?>" readonly>
                    <input type="hidden" name="doctor_id" value="<?php echo (int)$loggedDoctorId; ?>">
                </div>

                <div class="col-md-6">
                    <label for="available_date" class="form-label">Available Date</label>
                    <input type="date" class="form-control" id="available_date" name="available_date" value="<?php echo htmlspecialchars($available_date, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="col-md-6">
                    <label for="available_time" class="form-label">Available Time</label>
                    <input type="time" class="form-control" id="available_time" name="available_time" value="<?php echo htmlspecialchars($available_time, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Update Slot</button>
                    <a href="schedule_manage.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
