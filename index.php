<?php
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

if (!empty($_SESSION['is_doctor_logged_in']) && !empty($_SESSION['doctor_id'])) {
    header('Location: schedule_manage.php');
    exit;
}

if (!empty($_SESSION['is_admin_logged_in']) && !empty($_SESSION['admin_id'])) {
    if (!empty($_SESSION['admin_must_change_password'])) {
        header('Location: admin_change_password.php');
        exit;
    }

    header('Location: admin_dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Appointment System</title>
    <link rel="stylesheet" href="assets/css/refined-theme.css">
    <style>
        :root {
            --bg-a: #f3f9ff;
            --bg-b: #e8f8ef;
            --text: #0f172a;
            --muted: #64748b;
            --brand: #0f766e;
            --brand-hover: #0b615a;
            --border: #dbe7ef;
            --card-shadow: 0 20px 34px rgba(15, 23, 42, 0.12);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(140deg, var(--bg-a), var(--bg-b));
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .shell {
            width: min(1100px, 100%);
            display: grid;
            gap: 16px;
        }

        .hero {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: var(--card-shadow);
            padding: 24px;
            text-align: center;
        }

        h1 {
            margin: 0;
            font-size: clamp(1.7rem, 4vw, 2.3rem);
            letter-spacing: -0.03em;
        }

        .hero p {
            margin: 10px 0 0;
            color: var(--muted);
            font-size: 1rem;
        }

        .portal-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .portal {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            padding: 18px;
            display: grid;
            gap: 10px;
        }

        .portal h2 {
            margin: 0;
            font-size: 1.25rem;
        }

        .portal p {
            margin: 0;
            color: var(--muted);
            font-size: 0.93rem;
            min-height: 42px;
        }

        .portal a {
            text-decoration: none;
            background: var(--brand);
            color: #fff;
            font-weight: 700;
            border-radius: 10px;
            padding: 11px 12px;
            text-align: center;
            transition: background 0.2s ease;
        }

        .portal a:hover {
            background: var(--brand-hover);
        }

        @media (max-width: 900px) {
            .portal-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main class="shell">
        <section class="hero">
            <h1>Clinic Appointment System</h1>
            <p>Select your portal to continue.</p>
        </section>

        <section class="portal-grid">
            <article class="portal">
                <h2>Patient Portal</h2>
                <p>Book appointments, view available doctors, and manage your visits.</p>
                <a href="login.php">Open Patient Portal</a>
            </article>

            <article class="portal">
                <h2>Doctor Portal</h2>
                <p>Sign in to manage your availability and update your schedule.</p>
                <a href="doctor_login.php">Open Doctor Portal</a>
            </article>

            <article class="portal">
                <h2>Admin Portal</h2>
                <p>Manage doctors, appointments, security, and clinic reports.</p>
                <a href="admin_login.php">Open Admin Portal</a>
            </article>
        </section>
    </main>
</body>
</html>
