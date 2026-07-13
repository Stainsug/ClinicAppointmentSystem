<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/admin_auth.php';

$stats = [
    'patients' => 0,
    'doctors' => 0,
    'schedules' => 0,
    'appointments' => 0,
    'booked' => 0,
    'cancelled' => 0,
    'completed' => 0
];

$queries = [
    'patients' => 'SELECT COUNT(*) AS total FROM Patient',
    'doctors' => 'SELECT COUNT(*) AS total FROM Doctor',
    'schedules' => 'SELECT COUNT(*) AS total FROM Schedule',
    'appointments' => 'SELECT COUNT(*) AS total FROM Appointment',
    'booked' => "SELECT COUNT(*) AS total FROM Appointment WHERE status = 'Booked'",
    'cancelled' => "SELECT COUNT(*) AS total FROM Appointment WHERE status = 'Cancelled'",
    'completed' => "SELECT COUNT(*) AS total FROM Appointment WHERE status = 'Completed'"
];

foreach ($queries as $key => $sql) {
    $result = mysqli_query($conn, $sql);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $stats[$key] = (int)($row['total'] ?? 0);
    }
}

$upcomingSql = "SELECT d.fullname, d.specialization, COUNT(*) AS open_slots
                FROM Schedule s
                INNER JOIN Doctor d ON d.doctor_id = s.doctor_id
                WHERE (s.available_date > CURDATE() OR (s.available_date = CURDATE() AND s.available_time >= CURTIME()))
                  AND NOT EXISTS (
                      SELECT 1
                      FROM Appointment a
                      WHERE a.schedule_id = s.schedule_id
                        AND a.status = 'Booked'
                  )
                GROUP BY d.doctor_id, d.fullname, d.specialization
                ORDER BY open_slots DESC, d.fullname ASC
                LIMIT 5";

$topOpenSlots = [];
$upcomingResult = mysqli_query($conn, $upcomingSql);
if ($upcomingResult) {
    while ($row = mysqli_fetch_assoc($upcomingResult)) {
        $topOpenSlots[] = $row;
    }
}

$adminUsername = htmlspecialchars($loggedAdminUsername, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="assets/css/refined-theme.css">
    <style>
        :root {
            --bg-a: #f5f9ff;
            --bg-b: #e9f8f0;
            --panel: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #dbe7ef;
            --brand: #0f766e;
            --shadow: 0 14px 30px rgba(15, 23, 42, 0.1);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text);
            background:
                linear-gradient(140deg, rgba(245, 249, 255, 0.56), rgba(233, 248, 240, 0.56)),
                url('assets/images/admin-workspace-bg.svg') center/cover no-repeat fixed,
                linear-gradient(140deg, var(--bg-a), var(--bg-b));
            background-blend-mode: normal;
            padding: 22px;
        }

        .container {
            max-width: 1180px;
            margin: 0 auto;
            display: grid;
            gap: 16px;
        }

        .panel {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--shadow);
            padding: 22px;
        }

        .top-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        h1, h2 {
            margin: 0;
        }

        .muted {
            color: var(--muted);
            margin: 8px 0 0;
            font-size: 0.94rem;
        }

        .menu {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .menu a {
            text-decoration: none;
            background: #ffffff;
            color: #1e293b;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 9px 12px;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .menu a:hover {
            background: #f8fafc;
        }

        .menu a.active {
            background: #0f766e;
            color: #ffffff;
            border-color: #0f766e;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
            gap: 12px;
        }

        .card {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px;
            background: #ffffff;
        }

        .card h3 {
            margin: 0;
            font-size: 0.9rem;
            color: #334155;
            font-weight: 700;
        }

        .card .value {
            margin-top: 8px;
            font-size: 1.45rem;
            font-weight: 800;
            color: var(--brand);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border-bottom: 1px solid #e2e8f0;
            padding: 10px;
            text-align: left;
            font-size: 0.92rem;
        }

        th {
            background: #0f172a;
            color: #f8fafc;
        }

        @media (max-width: 740px) {
            body { padding: 14px; }
            .panel { padding: 16px; }
            .menu a { flex: 1; text-align: center; }
        }
    </style>
</head>
<body>
    <div class="container">
        <section class="panel">
            <div class="top-row">
                <div>
                    <h1>Admin Dashboard</h1>
                    <p class="muted">Signed in as <?php echo $adminUsername; ?>. Manage clinic data from one place.</p>
                </div>
                <?php
                    $adminNavMode = 'menu';
                    $adminActivePage = 'dashboard';
                    require __DIR__ . '/../../includes/partials/admin_nav.php';
                ?>
            </div>
        </section>

        <section class="panel">
            <h2 style="font-size:1.2rem;">System Overview</h2>
            <div class="cards" style="margin-top:12px;">
                <article class="card"><h3>Total Patients</h3><div class="value"><?php echo $stats['patients']; ?></div></article>
                <article class="card"><h3>Total Doctors</h3><div class="value"><?php echo $stats['doctors']; ?></div></article>
                <article class="card"><h3>Total Schedules</h3><div class="value"><?php echo $stats['schedules']; ?></div></article>
                <article class="card"><h3>Total Appointments</h3><div class="value"><?php echo $stats['appointments']; ?></div></article>
                <article class="card"><h3>Booked</h3><div class="value"><?php echo $stats['booked']; ?></div></article>
                <article class="card"><h3>Cancelled</h3><div class="value"><?php echo $stats['cancelled']; ?></div></article>
                <article class="card"><h3>Completed</h3><div class="value"><?php echo $stats['completed']; ?></div></article>
            </div>
        </section>

        <section class="panel">
            <h2 style="font-size:1.2rem;">Top Doctors With Open Slots</h2>
            <p class="muted">Upcoming unbooked slots only.</p>
            <div style="overflow-x:auto; margin-top:10px;">
                <table>
                    <thead>
                        <tr>
                            <th>Doctor</th>
                            <th>Specialization</th>
                            <th style="width:140px;">Open Slots</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($topOpenSlots)): ?>
                            <?php foreach ($topOpenSlots as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['fullname'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($row['specialization'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo (int)$row['open_slots']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" style="text-align:center; color:#64748b;">No open upcoming slots found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</body>
</html>
