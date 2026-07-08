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
    header('Location: admin_login.php');
    exit;
}

if (!empty($_SESSION['is_admin_logged_in']) && !empty($_SESSION['admin_id'])) {
    header('Location: admin_dashboard.php');
    exit;
}

if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!hash_equals($_SESSION['admin_csrf_token'], $csrfToken)) {
        $errors[] = 'Invalid request token. Please refresh and try again.';
    }

    if ($username === '') {
        $errors[] = 'Username is required.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    }

    if (empty($errors)) {
        $sql = 'SELECT admin_id, username, password FROM Admin WHERE username = ? LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt === false) {
            $errors[] = 'Admin login is not configured. Please verify the Admin table.';
        } else {
            mysqli_stmt_bind_param($stmt, 's', $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $admin = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            $isValid = false;
            $wasPlaintextLogin = false;
            $usedDefaultPassword = false;
            if ($admin) {
                $storedPassword = (string)$admin['password'];

                if ($storedPassword !== '' && password_verify($password, $storedPassword)) {
                    $isValid = true;
                } elseif (hash_equals($storedPassword, $password)) {
                    // Backward compatibility for existing plaintext seeds.
                    $isValid = true;
                    $wasPlaintextLogin = true;
                }

                if (hash_equals($password, 'admin123')) {
                    $usedDefaultPassword = true;
                }
            }

            if ($isValid) {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                if ($passwordHash !== false && ($wasPlaintextLogin || password_needs_rehash((string)$admin['password'], PASSWORD_DEFAULT))) {
                    $rehashSql = 'UPDATE Admin SET password = ? WHERE admin_id = ?';
                    $rehashStmt = mysqli_prepare($conn, $rehashSql);
                    if ($rehashStmt) {
                        $adminId = (int)$admin['admin_id'];
                        mysqli_stmt_bind_param($rehashStmt, 'si', $passwordHash, $adminId);
                        mysqli_stmt_execute($rehashStmt);
                        mysqli_stmt_close($rehashStmt);
                    }
                }

                session_regenerate_id(true);
                $_SESSION['is_admin_logged_in'] = true;
                $_SESSION['admin_id'] = (int)$admin['admin_id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_last_login_at'] = date('Y-m-d H:i:s');
                $_SESSION['admin_must_change_password'] = ($wasPlaintextLogin || $usedDefaultPassword);

                if (!empty($_SESSION['admin_must_change_password'])) {
                    header('Location: admin_change_password.php');
                    exit;
                }

                header('Location: admin_dashboard.php');
                exit;
            }

            $errors[] = 'Invalid admin username or password.';
        }
    }
}

$usernameValue = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="assets/css/refined-theme.css">
    <style>
        :root {
            --bg1: #eef7ff;
            --bg2: #e7f8ef;
            --panel: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --brand: #0f766e;
            --brand-hover: #0c6059;
            --danger-bg: #fee2e2;
            --danger-text: #b91c1c;
            --border: #dbe5ef;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background:
                linear-gradient(135deg, rgba(238, 247, 255, 0.58), rgba(231, 248, 239, 0.58)),
                url('assets/images/admin-auth-bg.svg') center/cover no-repeat fixed,
                linear-gradient(135deg, var(--bg1), var(--bg2));
            background-blend-mode: normal;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: var(--text);
        }

        .card {
            width: 100%;
            max-width: 450px;
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.12);
            padding: 26px;
        }

        h1 {
            margin: 0;
            font-size: 1.7rem;
        }

        .subtitle {
            margin: 8px 0 18px;
            color: var(--muted);
            font-size: 0.93rem;
        }

        .field {
            margin-bottom: 14px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        input {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 11px 12px;
            font-size: 0.94rem;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.16);
        }

        .btn {
            width: 100%;
            border: 0;
            border-radius: 10px;
            background: var(--brand);
            color: #ffffff;
            font-size: 0.97rem;
            font-weight: 700;
            padding: 12px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn:hover {
            background: var(--brand-hover);
        }

        .alert {
            border-radius: 10px;
            background: var(--danger-bg);
            color: var(--danger-text);
            padding: 12px;
            margin-bottom: 14px;
            font-size: 0.92rem;
        }

        .alert ul {
            margin: 0;
            padding-left: 18px;
        }

        .small-link {
            margin-top: 12px;
            text-align: center;
            color: var(--muted);
            font-size: 0.9rem;
        }

        .small-link a {
            color: var(--brand);
            text-decoration: none;
            font-weight: 600;
        }

        .small-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <main class="card">
        <h1>Admin Login</h1>
        <p class="subtitle">Sign in to manage doctors, appointments, and reports.</p>

        <?php if (!empty($errors)): ?>
            <div class="alert">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

            <div class="field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?php echo $usernameValue; ?>" maxlength="50" required>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button class="btn" type="submit">Login</button>
        </form>

        <p class="small-link">Patient login? <a href="login.php">Go to patient portal</a></p>
    </main>
</body>
</html>
