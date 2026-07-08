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

if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
    header('Location: doctor_login.php');
    exit;
}

if (!empty($_SESSION['is_doctor_logged_in']) && !empty($_SESSION['doctor_id'])) {
    header('Location: schedule_manage.php');
    exit;
}

if (empty($_SESSION['doctor_csrf_token'])) {
    $_SESSION['doctor_csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$successMessage = '';
$email = '';

if (isset($_GET['registered']) && $_GET['registered'] === '1') {
    $successMessage = 'Registration successful. Please log in with your new doctor account.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!hash_equals($_SESSION['doctor_csrf_token'], $csrfToken)) {
        $errors[] = 'Invalid request token. Please try again.';
    }

    if ($email === '') {
        $errors[] = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($password === '') {
        $errors[] = 'Please enter your password.';
    }

    if (empty($errors)) {
        $sql = 'SELECT doctor_id, fullname, email, password FROM Doctor WHERE email = ? LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt === false) {
            $errors[] = 'Doctor login is not configured. Please run doctor_login_setup.sql.';
        } else {
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $doctor = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if ($doctor && !empty($doctor['password']) && password_verify($password, $doctor['password'])) {
                session_regenerate_id(true);

                $_SESSION['is_doctor_logged_in'] = true;
                $_SESSION['doctor_id'] = (int)$doctor['doctor_id'];
                $_SESSION['doctor_name'] = $doctor['fullname'];
                $_SESSION['doctor_email'] = $doctor['email'];
                $_SESSION['doctor_last_login_at'] = date('Y-m-d H:i:s');

                header('Location: schedule_manage.php');
                exit;
            }

            $errors[] = 'Invalid doctor email or password.';
        }
    }
}

$emailValue = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Login</title>
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
                url('assets/images/doctor-auth-bg.svg') center/cover no-repeat fixed,
                linear-gradient(135deg, var(--bg-a), var(--bg-b));
            background-blend-mode: normal;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 18px;
            color: var(--text);
        }

        .login-card {
            width: 100%;
            max-width: 460px;
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
            margin-top: 12px;
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
    <main class="login-card">
        <div class="heading">
            <h1>Doctor Login</h1>
            <p>Access your availability scheduling panel.</p>
        </div>

        <?php if ($successMessage !== ''): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></div>
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

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['doctor_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" maxlength="100" value="<?php echo $emailValue; ?>" required>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>

            <button type="submit" class="btn btn-brand text-white w-100">Login</button>
        </form>

        <p class="aux-link mb-1">New doctor or new clinic? <a href="doctor_register.php">Create doctor account</a></p>

        <p class="aux-link">Patient account? <a href="login.php">Go to patient login</a></p>
    </main>
</body>
</html>
