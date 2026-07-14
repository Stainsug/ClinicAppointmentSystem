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

if (empty($_SESSION['patient_register_csrf_token'])) {
    $_SESSION['patient_register_csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$successMessage = '';

$fullname = '';
$email = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!hash_equals($_SESSION['patient_register_csrf_token'], $csrfToken)) {
        $errors[] = 'Invalid request token. Please refresh and try again.';
    }

    if ($fullname === '') {
        $errors[] = 'Full name is required.';
    } elseif (strlen($fullname) < 3 || strlen($fullname) > 100) {
        $errors[] = 'Full name must be between 3 and 100 characters.';
    }

    if ($email === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($phone === '') {
        $errors[] = 'Phone number is required.';
    } elseif (!preg_match('/^[0-9+\-()\s]{7,20}$/', $phone)) {
        $errors[] = 'Phone number format is invalid.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    } elseif (
        strlen($password) < 8 ||
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[a-z]/', $password) ||
        !preg_match('/[0-9]/', $password)
    ) {
        $errors[] = 'Password must be at least 8 characters and include uppercase, lowercase, and a number.';
    }

    if ($confirmPassword === '') {
        $errors[] = 'Please confirm your password.';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $checkSql = 'SELECT patient_id FROM Patient WHERE email = ? LIMIT 1';
        $checkStmt = $conn->prepare($checkSql);

        if (!$checkStmt) {
            $errors[] = 'Unable to process registration at the moment.';
        } else {
            $checkStmt->bind_param('s', $email);
            $checkStmt->execute();
            $checkStmt->store_result();

            if ($checkStmt->num_rows > 0) {
                $errors[] = 'An account with this email already exists.';
            }

            $checkStmt->close();
        }
    }

    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $insertSql = 'INSERT INTO Patient (fullname, email, phone, password) VALUES (?, ?, ?, ?)';
        $insertStmt = $conn->prepare($insertSql);

        if (!$insertStmt) {
            $errors[] = 'Unable to process registration at the moment.';
        } else {
            $insertStmt->bind_param('ssss', $fullname, $email, $phone, $hashedPassword);

            if ($insertStmt->execute()) {
                $successMessage = 'Registration successful. You can now log in.';
                $fullname = '';
                $email = '';
                $phone = '';
            } else {
                $errors[] = 'Registration failed. Please try again.';
            }

            $insertStmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Registration</title>
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
                url('assets/images/patient-auth-custom.png') center/cover no-repeat fixed,
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
            max-width: 520px;
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

        .helper {
            margin-top: 6px;
            color: var(--muted);
            font-size: 0.82rem;
        }

        button {
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
            transition: background 0.2s;
        }

        button:hover {
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
        <h1>Patient Registration</h1>
        <p class="subtitle">Create your account to book clinic appointments.</p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($successMessage !== ''): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($successMessage); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['patient_register_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            <div class="field">
                <label for="fullname">Full Name</label>
                <input
                    type="text"
                    id="fullname"
                    name="fullname"
                    maxlength="100"
                    value="<?php echo htmlspecialchars($fullname); ?>"
                    required
                >
            </div>

            <div class="field">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="100"
                    value="<?php echo htmlspecialchars($email); ?>"
                    required
                >
            </div>

            <div class="field">
                <label for="phone">Phone Number</label>
                <input
                    type="text"
                    id="phone"
                    name="phone"
                    maxlength="20"
                    value="<?php echo htmlspecialchars($phone); ?>"
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
                <p class="helper">Minimum 8 characters, with uppercase, lowercase, and a number.</p>
            </div>

            <div class="field">
                <label for="confirm_password">Confirm Password</label>
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    required
                >
            </div>

            <button type="submit">Register</button>
        </form>

        <p class="small-link">Existing patient? <a href="login.php">Log in</a></p>
    </main>
</body>
</html>
