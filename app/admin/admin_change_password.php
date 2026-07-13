<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/admin_auth.php';

if (empty($_SESSION['admin_pw_csrf_token'])) {
    $_SESSION['admin_pw_csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!hash_equals($_SESSION['admin_pw_csrf_token'], $csrfToken)) {
        $errors[] = 'Invalid request token. Please refresh and try again.';
    }

    if ($currentPassword === '') {
        $errors[] = 'Current password is required.';
    }

    if ($newPassword === '') {
        $errors[] = 'New password is required.';
    } elseif (
        strlen($newPassword) < 10 ||
        !preg_match('/[A-Z]/', $newPassword) ||
        !preg_match('/[a-z]/', $newPassword) ||
        !preg_match('/[0-9]/', $newPassword)
    ) {
        $errors[] = 'New password must be at least 10 characters and include uppercase, lowercase, and a number.';
    }

    if ($confirmPassword === '') {
        $errors[] = 'Please confirm your new password.';
    } elseif (!hash_equals($newPassword, $confirmPassword)) {
        $errors[] = 'New password and confirmation do not match.';
    }

    if (empty($errors)) {
        $selectSql = 'SELECT password FROM Admin WHERE admin_id = ? LIMIT 1';
        $selectStmt = mysqli_prepare($conn, $selectSql);

        if ($selectStmt === false) {
            $errors[] = 'Unable to validate current password.';
        } else {
            mysqli_stmt_bind_param($selectStmt, 'i', $loggedAdminId);
            mysqli_stmt_execute($selectStmt);
            $result = mysqli_stmt_get_result($selectStmt);
            $admin = mysqli_fetch_assoc($result);
            mysqli_stmt_close($selectStmt);

            if (!$admin) {
                $errors[] = 'Admin account not found.';
            } else {
                $storedPassword = (string)$admin['password'];
                $currentMatches = false;

                if ($storedPassword !== '' && password_verify($currentPassword, $storedPassword)) {
                    $currentMatches = true;
                } elseif (hash_equals($storedPassword, $currentPassword)) {
                    $currentMatches = true;
                }

                if (!$currentMatches) {
                    $errors[] = 'Current password is incorrect.';
                } elseif (hash_equals($currentPassword, $newPassword)) {
                    $errors[] = 'New password must be different from the current password.';
                }
            }
        }
    }

    if (empty($errors)) {
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        if ($newHash === false) {
            $errors[] = 'Could not secure your new password. Please try again.';
        } else {
            $updateSql = 'UPDATE Admin SET password = ? WHERE admin_id = ?';
            $updateStmt = mysqli_prepare($conn, $updateSql);

            if ($updateStmt === false) {
                $errors[] = 'Unable to update password right now.';
            } else {
                mysqli_stmt_bind_param($updateStmt, 'si', $newHash, $loggedAdminId);
                mysqli_stmt_execute($updateStmt);
                mysqli_stmt_close($updateStmt);

                $_SESSION['admin_must_change_password'] = false;
                $success = 'Password updated successfully.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Change Password</title>
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
            --success-bg: #dcfce7;
            --success-text: #166534;
            --border: #dbe5ef;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background:
                linear-gradient(135deg, rgba(238, 247, 255, 0.56), rgba(231, 248, 239, 0.56)),
                url('assets/images/admin-workspace-bg.svg') center/cover no-repeat fixed,
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
            max-width: 520px;
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.12);
            padding: 26px;
        }

        h1 { margin: 0; font-size: 1.6rem; }
        .subtitle { margin: 8px 0 16px; color: var(--muted); font-size: 0.92rem; }

        .alert {
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 14px;
            font-size: 0.92rem;
        }

        .alert-danger { background: var(--danger-bg); color: var(--danger-text); }
        .alert-success { background: var(--success-bg); color: var(--success-text); }
        .alert ul { margin: 0; padding-left: 18px; }

        .field { margin-bottom: 14px; }
        label { display: block; margin-bottom: 6px; font-size: 0.9rem; font-weight: 600; }

        input {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 11px 12px;
            font-size: 0.94rem;
            outline: none;
        }

        input:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.16);
        }

        .actions { display: flex; gap: 10px; flex-wrap: wrap; }

        .btn {
            border: 0;
            border-radius: 10px;
            padding: 11px 14px;
            font-size: 0.95rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-primary { background: var(--brand); color: #fff; }
        .btn-primary:hover { background: var(--brand-hover); }

        .btn-outline {
            background: #fff;
            color: #1e293b;
            border: 1px solid #cbd5e1;
        }
    </style>
</head>
<body>
    <main class="card">
        <h1>Change Admin Password</h1>
        <p class="subtitle">For security, please set a new password before continuing.</p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_pw_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

            <div class="field">
                <label for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password" required>
            </div>

            <div class="field">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" required>
            </div>

            <div class="field">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Update Password</button>
                <?php if (empty($_SESSION['admin_must_change_password'])): ?>
                    <a class="btn btn-outline" href="admin_dashboard.php">Back to Dashboard</a>
                <?php endif; ?>
            </div>
        </form>
    </main>
</body>
</html>
