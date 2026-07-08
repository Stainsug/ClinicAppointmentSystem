<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/admin_auth.php';

$errors = [];
$fullname = '';
$specialization = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($fullname === '') {
        $errors[] = 'Full name is required.';
    } elseif (strlen($fullname) > 100) {
        $errors[] = 'Full name must not exceed 100 characters.';
    }

    if ($specialization === '') {
        $errors[] = 'Specialization is required.';
    } elseif (strlen($specialization) > 100) {
        $errors[] = 'Specialization must not exceed 100 characters.';
    }

    if ($email === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (strlen($email) > 100) {
        $errors[] = 'Email must not exceed 100 characters.';
    }

    if (empty($errors)) {
        $checkSql = 'SELECT doctor_id FROM Doctor WHERE email = ? LIMIT 1';
        $checkStmt = mysqli_prepare($conn, $checkSql);

        if ($checkStmt === false) {
            $errors[] = 'Unable to validate doctor email right now.';
        } else {
            mysqli_stmt_bind_param($checkStmt, 's', $email);
            mysqli_stmt_execute($checkStmt);
            mysqli_stmt_store_result($checkStmt);

            if (mysqli_stmt_num_rows($checkStmt) > 0) {
                $errors[] = 'A doctor with this email already exists.';
            }

            mysqli_stmt_close($checkStmt);
        }
    }

    if (empty($errors)) {
        $insertSql = 'INSERT INTO Doctor (fullname, specialization, email) VALUES (?, ?, ?)';
        $insertStmt = mysqli_prepare($conn, $insertSql);

        if ($insertStmt === false) {
            $errors[] = 'Unable to add doctor right now.';
        } else {
            mysqli_stmt_bind_param($insertStmt, 'sss', $fullname, $specialization, $email);

            if (mysqli_stmt_execute($insertStmt)) {
                mysqli_stmt_close($insertStmt);
                header('Location: admin_doctors.php?success=added');
                exit;
            }

            mysqli_stmt_close($insertStmt);
            $errors[] = 'Failed to add doctor. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Add Doctor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/refined-theme.css">
    <style>
        body { background: linear-gradient(135deg, #f3f8ff, #e7f8ee); min-height: 100vh; }
        .page-wrap { max-width: 760px; margin: 34px auto; padding: 0 16px; }
        .panel { background: #fff; border-radius: 14px; box-shadow: 0 12px 30px rgba(0,0,0,0.08); padding: 24px; }
    </style>
</head>
<body>
    <div class="page-wrap">
        <div class="panel">
            <h2 class="mb-3">Admin - Add Doctor</h2>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label for="fullname" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="fullname" name="fullname" maxlength="100" value="<?php echo htmlspecialchars($fullname, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="specialization" class="form-label">Specialization</label>
                    <input type="text" class="form-control" id="specialization" name="specialization" maxlength="100" value="<?php echo htmlspecialchars($specialization, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" maxlength="100" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success">Save Doctor</button>
                    <a href="admin_doctors.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
