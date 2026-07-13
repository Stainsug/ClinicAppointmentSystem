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

if (!empty($_SESSION['is_doctor_logged_in']) && !empty($_SESSION['doctor_id'])) {
    header('Location: schedule_manage.php');
    exit;
}

if (empty($_SESSION['doctor_register_csrf_token'])) {
    $_SESSION['doctor_register_csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];

$fullname = '';
$specialization = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $fullname = trim($_POST['fullname'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!hash_equals($_SESSION['doctor_register_csrf_token'], $csrfToken)) {
        $errors[] = 'Invalid request token. Please try again.';
    }

    if ($fullname === '') {
        $errors[] = 'Full name is required.';
    } elseif (strlen($fullname) < 3 || strlen($fullname) > 100) {
        $errors[] = 'Full name must be between 3 and 100 characters.';
    }

    if ($specialization === '') {
        $errors[] = 'Profession is required.';
    } elseif (strlen($specialization) < 2 || strlen($specialization) > 100) {
        $errors[] = 'Profession must be between 2 and 100 characters.';
    }

    if ($email === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (strlen($email) > 100) {
        $errors[] = 'Email must not exceed 100 characters.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    } elseif (
        strlen($password) < 8
        || !preg_match('/[A-Z]/', $password)
        || !preg_match('/[a-z]/', $password)
        || !preg_match('/[0-9]/', $password)
    ) {
        $errors[] = 'Password must be at least 8 characters and include uppercase, lowercase, and a number.';
    }

    if ($confirmPassword === '') {
        $errors[] = 'Please confirm your password.';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $checkSql = 'SELECT doctor_id FROM Doctor WHERE email = ? LIMIT 1';
        $checkStmt = mysqli_prepare($conn, $checkSql);

        if ($checkStmt === false) {
            $errors[] = 'Unable to verify email right now.';
        } else {
            mysqli_stmt_bind_param($checkStmt, 's', $email);
            mysqli_stmt_execute($checkStmt);
            mysqli_stmt_store_result($checkStmt);

            if (mysqli_stmt_num_rows($checkStmt) > 0) {
                $errors[] = 'A doctor account with this email already exists.';
            }

            mysqli_stmt_close($checkStmt);
        }
    }

    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $insertSql = 'INSERT INTO Doctor (fullname, specialization, email, password) VALUES (?, ?, ?, ?)';
        $insertStmt = mysqli_prepare($conn, $insertSql);

        if ($insertStmt === false) {
            $errors[] = 'Doctor registration is not configured. Please run doctor_login_setup.sql first.';
        } else {
            mysqli_stmt_bind_param($insertStmt, 'ssss', $fullname, $specialization, $email, $hashedPassword);

            if (mysqli_stmt_execute($insertStmt)) {
                mysqli_stmt_close($insertStmt);
                header('Location: doctor_login.php?registered=1');
                exit;
            }

            $dbErrorCode = mysqli_errno($conn);
            mysqli_stmt_close($insertStmt);

            if ($dbErrorCode === 1062) {
                $errors[] = 'A doctor account with this email already exists.';
            } elseif ($dbErrorCode === 1054) {
                $errors[] = 'Doctor password column is missing. Please run doctor_login_setup.sql.';
            } else {
                $errors[] = 'Registration failed. Please try again.';
            }
        }
    }
}

$fullnameValue = htmlspecialchars($fullname, ENT_QUOTES, 'UTF-8');
$specializationValue = htmlspecialchars($specialization, ENT_QUOTES, 'UTF-8');
$emailValue = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/refined-theme.css">
    <style>
        :root {
            --bg-a: #f5f8ff;
            --bg-b: #e7f7ef;
            --panel: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --brand: #0f766e;
            --brand-hover: #0a5b55;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background:
                linear-gradient(135deg, rgba(245, 248, 255, 0.56), rgba(231, 247, 239, 0.56)),
                url('assets/images/doctor-workspace-bg.svg') center/cover no-repeat fixed,
                linear-gradient(135deg, var(--bg-a), var(--bg-b));
            background-blend-mode: normal;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 18px;
            color: var(--text);
        }

        .register-card {
            width: 100%;
            max-width: 540px;
            background: var(--panel);
            border-radius: 16px;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.15);
            padding: 28px;
        }

        .heading {
            margin-bottom: 18px;
        }

        .heading h1 {
            margin: 0;
            font-size: 1.7rem;
        }

        .heading p {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: 0.95rem;
        }

        .btn-brand {
            background: var(--brand);
            border-color: var(--brand);
            font-weight: 600;
        }

        .btn-brand:hover {
            background: var(--brand-hover);
            border-color: var(--brand-hover);
        }

        .aux-link {
            margin-top: 14px;
            text-align: center;
            font-size: 0.9rem;
            color: var(--muted);
        }

        .aux-link a {
            color: var(--brand);
            text-decoration: none;
            font-weight: 600;
        }

        .aux-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <main class="register-card">
        <div class="heading">
            <h1>Doctor Registration</h1>
            <p>Create a doctor account and start managing appointment availability.</p>
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

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['doctor_register_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

            <div class="mb-3">
                <label for="fullname" class="form-label">Full Name</label>
                <input type="text" class="form-control" id="fullname" name="fullname" maxlength="100" value="<?php echo $fullnameValue; ?>" required>
            </div>

            <div class="mb-3">
                <label for="specialization" class="form-label">Profession (Specialization)</label>
                <input type="text" class="form-control" id="specialization" name="specialization" maxlength="100" value="<?php echo $specializationValue; ?>" required>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" maxlength="100" value="<?php echo $emailValue; ?>" required>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
                <div class="form-text">Minimum 8 characters, include uppercase, lowercase, and number.</div>
            </div>

            <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
            </div>

            <button type="submit" class="btn btn-brand text-white w-100">Create Doctor Account</button>
        </form>

        <p class="aux-link">Already registered? <a href="doctor_login.php">Go to doctor login</a></p>
    </main>
</body>
</html>
