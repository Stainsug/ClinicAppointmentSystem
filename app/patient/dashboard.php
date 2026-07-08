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

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
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
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['is_patient_logged_in']) || empty($_SESSION['patient_id'])) {
    header('Location: login.php');
    exit;
}

$patientName = htmlspecialchars($_SESSION['patient_name'] ?? 'Patient', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard</title>
    <link rel="stylesheet" href="assets/css/refined-theme.css">
    <style>
        :root {
            --bg-start: #f6fbff;
            --bg-end: #e8f5ed;
            --surface: #ffffff;
            --sidebar: #0f172a;
            --sidebar-muted: #94a3b8;
            --sidebar-active: #134e4a;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --accent: #0f766e;
            --accent-soft: #ccfbf1;
            --border: #e2e8f0;
            --shadow: 0 20px 40px rgba(15, 23, 42, 0.12);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background:
                linear-gradient(140deg, rgba(246, 251, 255, 0.52), rgba(232, 245, 237, 0.52)),
                url('assets/images/patient-dashboard-bg.svg') center/cover no-repeat fixed,
                linear-gradient(140deg, var(--bg-start), var(--bg-end));
            background-blend-mode: normal;
            color: var(--text-main);
            min-height: 100vh;
        }

        .layout {
            min-height: 100vh;
            display: flex;
        }

        .sidebar {
            width: 260px;
            background: var(--sidebar);
            color: #ffffff;
            padding: 24px 18px;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .brand {
            padding: 10px 10px 0;
        }

        .brand h2 {
            margin: 0;
            font-size: 1.2rem;
            letter-spacing: 0.4px;
        }

        .brand p {
            margin: 6px 0 0;
            color: var(--sidebar-muted);
            font-size: 0.85rem;
        }

        .nav {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .nav a {
            color: #e2e8f0;
            text-decoration: none;
            padding: 11px 12px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: background 0.2s, color 0.2s;
        }

        .nav a:hover {
            background: rgba(148, 163, 184, 0.2);
        }

        .nav a.active {
            background: var(--sidebar-active);
            color: #ffffff;
        }

        .nav a.doctor-register {
            background: rgba(20, 184, 166, 0.22);
            color: #99f6e4;
        }

        .nav a.doctor-register:hover {
            background: rgba(20, 184, 166, 0.38);
            color: #ccfbf1;
        }

        .nav a.logout {
            margin-top: 12px;
            color: #fecaca;
        }

        .nav a.logout:hover {
            background: rgba(127, 29, 29, 0.35);
            color: #fee2e2;
        }

        .content {
            flex: 1;
            padding: 30px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .topbar {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px 20px;
            box-shadow: var(--shadow);
        }

        .topbar h1 {
            margin: 0;
            font-size: 1.6rem;
        }

        .topbar p {
            margin: 8px 0 0;
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px;
            box-shadow: var(--shadow);
        }

        .card h3 {
            margin: 0 0 8px;
            font-size: 1rem;
        }

        .card p {
            margin: 0;
            color: var(--text-muted);
            font-size: 0.9rem;
            line-height: 1.45;
        }

        .welcome {
            background: linear-gradient(140deg, #ecfeff, #f0fdfa);
            border: 1px solid #99f6e4;
            border-radius: 14px;
            padding: 22px;
        }

        .welcome h2 {
            margin: 0 0 8px;
            font-size: 1.3rem;
            color: #134e4a;
        }

        .welcome p {
            margin: 0;
            color: #0f766e;
        }

        @media (max-width: 980px) {
            .cards {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 820px) {
            .layout {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                padding: 18px;
            }

            .nav {
                flex-direction: row;
                flex-wrap: wrap;
            }

            .nav a {
                flex: 1 1 170px;
                text-align: center;
            }

            .content {
                padding: 18px;
            }
        }

        @media (max-width: 560px) {
            .cards {
                grid-template-columns: 1fr;
            }

            .topbar h1 {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>
    <div class="layout">
        <aside class="sidebar">
            <div class="brand">
                <h2>Clinic Care</h2>
                <p>Patient Portal</p>
            </div>

            <nav class="nav">
                <a class="active" href="dashboard.php">Dashboard</a>
                <a href="doctors.php">View Doctors</a>
                <a href="book_appointments.php">Book Appointment</a>
                <a href="my_appointments.php">My Appointments</a>
                <a class="doctor-register" href="admin_login.php">Admin Portal</a>
                <a class="logout" href="dashboard.php?action=logout">Logout</a>
            </nav>
        </aside>

        <main class="content">
            <section class="topbar">
                <h1>Welcome, <?php echo $patientName; ?></h1>
                <p>Manage your appointments, review doctor availability, and stay updated with your clinic visits.</p>
            </section>

            <section class="welcome">
                <h2>Good to see you, <?php echo $patientName; ?>.</h2>
                <p>Your account is active and ready. Use the menu to book a new appointment or track existing bookings.</p>
            </section>

            <section class="cards">
                <article class="card">
                    <h3>View Doctors</h3>
                    <p>Browse available specialists and review their profiles before scheduling your consultation.</p>
                </article>

                <article class="card">
                    <h3>Book Appointment</h3>
                    <p>Choose a date and time slot that matches your needs and reserve it instantly.</p>
                </article>

                <article class="card">
                    <h3>My Appointments</h3>
                    <p>Track upcoming visits, check status updates, and manage your existing bookings.</p>
                </article>
            </section>
        </main>
    </div>
</body>
</html>
