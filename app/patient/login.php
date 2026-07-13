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

if (!empty($_SESSION['is_patient_logged_in']) && !empty($_SESSION['patient_id'])) {
    header('Location: dashboard.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $errors[] = 'Your session request is invalid. Please try again.';
    }

    if ($email === '') {
        $errors[] = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }

    if ($password === '') {
        $errors[] = 'Please enter your password.';
    }

    if (empty($errors)) {
        $sql = 'SELECT patient_id, fullname, email, password FROM Patient WHERE email = ? LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt === false) {
            $errors[] = 'Login is temporarily unavailable. Please try again shortly.';
        } else {
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $patient = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if ($patient && password_verify($password, $patient['password'])) {
                session_regenerate_id(true);

                $_SESSION['patient_id'] = (int)$patient['patient_id'];
                $_SESSION['patient_name'] = $patient['fullname'];
                $_SESSION['patient_email'] = $patient['email'];
                $_SESSION['is_patient_logged_in'] = true;
                $_SESSION['last_login_at'] = date('Y-m-d H:i:s');

                header('Location: dashboard.php');
                exit;
            }

            $errors[] = 'Invalid email or password. Please check your details and try again.';
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
    <title>Patient Login</title>
    <link rel="stylesheet" href="assets/css/refined-theme.css">
    <style>
        :root {
            --bg1: #f0f7ff;
            --bg2: #dff0e8;
            --panel: #ffffff;
            --text: #1f2937;
            --muted: #6b7280;
            --brand: #0f766e;
            --brand-hover: #0b5f59;
            --error-bg: #fee2e2;
            --error-text: #991b1b;
            --success-bg: #dcfce7;
            --success-text: #166534;
            --border: #d1d5db;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background:
                linear-gradient(130deg, rgba(240, 247, 255, 0.58), rgba(223, 240, 232, 0.58)),
                url('assets/images/patient-workspace-bg.svg') center/cover no-repeat fixed,
                linear-gradient(130deg, var(--bg1), var(--bg2));
            background-blend-mode: normal;
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .card {
            width: 100%;
            max-width: 480px;
            background: var(--panel);
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(15, 118, 110, 0.15);
            padding: 28px;
        }

        h1 {
            margin: 0 0 8px;
            font-size: 1.8rem;
        }

        .subtitle {
            margin: 0 0 20px;
            color: var(--muted);
            font-size: 0.95rem;
        }

        .alert {
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 16px;
            font-size: 0.93rem;
        }

        .alert-error {
            background: var(--error-bg);
            color: var(--error-text);
        }

        .alert-success {
            background: var(--success-bg);
            color: var(--success-text);
        }

        .alert ul {
            margin: 0;
            padding-left: 18px;
        }

        .field {
            margin-bottom: 14px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 0.92rem;
        }

        input {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 11px 12px;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.15);
        }

        button,
        .link-button {
            width: 100%;
            border: 0;
            border-radius: 10px;
            background: var(--brand);
            color: #ffffff;
            padding: 12px;
            margin-top: 6px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: background 0.2s;
        }

        button:hover,
        .link-button:hover {
            background: var(--brand-hover);
        }

        .small-link {
            margin-top: 14px;
            text-align: center;
            font-size: 0.9rem;
            color: var(--muted);
        }

        .small-link a {
            color: var(--brand);
            text-decoration: none;
            font-weight: 600;
        }

        .small-link a:hover {
            text-decoration: underline;
        }

        @media (max-width: 560px) {
            .card {
                padding: 22px;
            }

            h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <main class="card">
        <h1>Patient Login</h1>
        <p class="subtitle">Sign in to manage your clinic appointments.</p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

            <div class="field">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="100"
                    value="<?php echo $emailValue; ?>"
                    required
                >
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                >
            </div>

            <button type="submit">Login</button>
        </form>

        <p class="small-link">New patient? <a href="register.php">Create an account</a></p>
        <p class="small-link">Doctor account? <a href="doctor_login.php">Go to doctor login</a></p>
        <p class="small-link">Admin access? <a href="admin_login.php">Go to admin login</a></p>
    </main>
</body>
</html>
