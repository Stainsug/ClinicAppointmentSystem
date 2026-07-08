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

if (empty($_SESSION['is_patient_logged_in']) || empty($_SESSION['patient_id'])) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['book_csrf_token'])) {
    $_SESSION['book_csrf_token'] = bin2hex(random_bytes(32));
}

$patientId = (int)$_SESSION['patient_id'];
$patientName = htmlspecialchars($_SESSION['patient_name'] ?? 'Patient', ENT_QUOTES, 'UTF-8');
$errors = [];
$successMessage = '';

if (isset($_GET['success']) && $_GET['success'] === '1') {
    $successMessage = 'Appointment booked successfully.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $scheduleId = (int)($_POST['schedule_id'] ?? 0);

    if (!hash_equals($_SESSION['book_csrf_token'], $csrfToken)) {
        $errors[] = 'Invalid request token. Please refresh and try again.';
    }

    if ($scheduleId <= 0) {
        $errors[] = 'Please choose a valid slot to book.';
    }

    if (empty($errors)) {
        $slotSql = "SELECT s.schedule_id, s.available_date, s.available_time, d.fullname, d.specialization,
                           CASE
                               WHEN EXISTS (
                                   SELECT 1
                                   FROM Appointment a
                                   WHERE a.schedule_id = s.schedule_id
                                     AND a.status = 'Booked'
                               ) THEN 1
                               ELSE 0
                           END AS is_booked
                    FROM Schedule s
                    INNER JOIN Doctor d ON d.doctor_id = s.doctor_id
                    WHERE s.schedule_id = ?
                    LIMIT 1";

        $slotStmt = mysqli_prepare($conn, $slotSql);

        if ($slotStmt === false) {
            $errors[] = 'Could not validate the selected slot. Please try again.';
        } else {
            mysqli_stmt_bind_param($slotStmt, 'i', $scheduleId);
            mysqli_stmt_execute($slotStmt);
            $slotResult = mysqli_stmt_get_result($slotStmt);
            $slot = mysqli_fetch_assoc($slotResult);
            mysqli_stmt_close($slotStmt);

            if (!$slot) {
                $errors[] = 'The selected slot was not found.';
            } else {
                $slotTimestamp = strtotime($slot['available_date'] . ' ' . $slot['available_time']);

                if ($slot['is_booked']) {
                    $errors[] = 'That slot has already been booked. Please choose another slot.';
                } elseif ($slotTimestamp !== false && $slotTimestamp < time()) {
                    $errors[] = 'That slot is in the past and is no longer available.';
                } else {
                    $bookSql = "INSERT INTO Appointment (patient_id, schedule_id, appointment_date, status)
                                SELECT ?, s.schedule_id, s.available_date, 'Booked'
                                FROM Schedule s
                                WHERE s.schedule_id = ?
                                  AND NOT EXISTS (
                                      SELECT 1
                                      FROM Appointment a
                                      WHERE a.schedule_id = s.schedule_id
                                        AND a.status = 'Booked'
                                  )
                                LIMIT 1";

                    $bookStmt = mysqli_prepare($conn, $bookSql);

                    if ($bookStmt === false) {
                        $errors[] = 'Booking service is unavailable right now. Please try again.';
                    } else {
                        mysqli_stmt_bind_param($bookStmt, 'ii', $patientId, $scheduleId);
                        mysqli_stmt_execute($bookStmt);
                        $affectedRows = mysqli_stmt_affected_rows($bookStmt);
                        $insertError = mysqli_errno($conn);
                        mysqli_stmt_close($bookStmt);

                        if ($affectedRows === 1) {
                            header('Location: book_appointments.php?success=1');
                            exit;
                        }

                        if ($insertError === 1062) {
                            $errors[] = 'You already booked this slot.';
                        } else {
                            $errors[] = 'That slot is no longer available. Please pick another one.';
                        }
                    }
                }
            }
        }
    }
}

$doctorCards = [];
$cardsSql = "SELECT d.doctor_id, d.fullname, d.specialization,
                    COUNT(s.schedule_id) AS total_slots,
                    SUM(
                        CASE
                            WHEN s.schedule_id IS NOT NULL
                             AND (s.available_date > CURDATE() OR (s.available_date = CURDATE() AND s.available_time >= CURTIME()))
                            THEN 1
                            ELSE 0
                        END
                    ) AS upcoming_slots,
                    SUM(
                        CASE
                            WHEN s.schedule_id IS NOT NULL
                             AND (s.available_date > CURDATE() OR (s.available_date = CURDATE() AND s.available_time >= CURTIME()))
                             AND NOT EXISTS (
                                 SELECT 1
                                 FROM Appointment a
                                 WHERE a.schedule_id = s.schedule_id
                                   AND a.status = 'Booked'
                             )
                            THEN 1
                            ELSE 0
                        END
                    ) AS available_slots
             FROM Doctor d
             LEFT JOIN Schedule s ON s.doctor_id = d.doctor_id
             GROUP BY d.doctor_id, d.fullname, d.specialization
             ORDER BY d.fullname ASC";

$cardsResult = mysqli_query($conn, $cardsSql);
if ($cardsResult) {
    while ($card = mysqli_fetch_assoc($cardsResult)) {
        $doctorCards[] = $card;
    }
}

$slots = [];
$slotsSql = "SELECT s.schedule_id, d.fullname, d.specialization, s.available_date, s.available_time,
                    CASE
                        WHEN EXISTS (
                            SELECT 1
                            FROM Appointment a
                            WHERE a.schedule_id = s.schedule_id
                              AND a.status = 'Booked'
                        ) THEN 1
                        ELSE 0
                    END AS is_booked,
                    CASE
                        WHEN s.available_date > CURDATE() OR (s.available_date = CURDATE() AND s.available_time >= CURTIME())
                        THEN 1
                        ELSE 0
                    END AS is_upcoming
             FROM Schedule s
             INNER JOIN Doctor d ON d.doctor_id = s.doctor_id
             ORDER BY s.available_date ASC, s.available_time ASC, d.fullname ASC";

$slotsResult = mysqli_query($conn, $slotsSql);
if ($slotsResult) {
    while ($row = mysqli_fetch_assoc($slotsResult)) {
        $slots[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment</title>
    <link rel="stylesheet" href="assets/css/refined-theme.css">
    <style>
        :root {
            --bg-a: #f7fbff;
            --bg-b: #ecfdf5;
            --panel: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #dbe7ef;
            --brand: #0f766e;
            --brand-hover: #0c5f58;
            --danger: #b91c1c;
            --danger-bg: #fee2e2;
            --success: #166534;
            --success-bg: #dcfce7;
            --shadow: 0 16px 36px rgba(15, 23, 42, 0.12);
            --dot-green: #16a34a;
            --dot-red: #dc2626;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--bg-a), var(--bg-b));
            color: var(--text);
            padding: 24px;
        }

        .page-wrap {
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

        .heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 8px;
        }

        .heading h1 {
            margin: 0;
            font-size: 1.7rem;
        }

        .muted {
            margin: 0;
            color: var(--muted);
            font-size: 0.95rem;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .btn {
            text-decoration: none;
            border: 0;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.92rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-outline {
            background: #ffffff;
            color: #1e293b;
            border: 1px solid #cbd5e1;
        }

        .btn-outline:hover {
            background: #f8fafc;
        }

        .btn-brand {
            background: var(--brand);
            color: #ffffff;
        }

        .btn-brand:hover {
            background: var(--brand-hover);
        }

        .alert {
            border-radius: 10px;
            padding: 12px 14px;
            margin-top: 14px;
            font-size: 0.92rem;
        }

        .alert-danger {
            background: var(--danger-bg);
            color: var(--danger);
        }

        .alert-success {
            background: var(--success-bg);
            color: var(--success);
        }

        .alert ul {
            margin: 0;
            padding-left: 18px;
        }

        .doctor-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 14px;
            margin-top: 14px;
        }

        .doctor-card {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px;
            background: #ffffff;
        }

        .doctor-card h3 {
            margin: 0 0 6px;
            font-size: 1.03rem;
        }

        .chip {
            display: inline-block;
            border-radius: 999px;
            padding: 5px 10px;
            font-size: 0.78rem;
            font-weight: 700;
            background: #e0f2fe;
            color: #075985;
            margin-bottom: 10px;
        }

        .status-line {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.92rem;
            font-weight: 600;
        }

        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.04);
        }

        .status-dot.available {
            background: var(--dot-green);
        }

        .status-dot.unavailable {
            background: var(--dot-red);
        }

        .stats {
            margin-top: 10px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .stat {
            font-size: 0.8rem;
            color: #334155;
            background: #f1f5f9;
            border-radius: 8px;
            padding: 4px 8px;
        }

        .slots-table-wrap {
            margin-top: 14px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 760px;
        }

        th,
        td {
            border-bottom: 1px solid #e2e8f0;
            padding: 11px 10px;
            text-align: left;
            font-size: 0.92rem;
            vertical-align: middle;
        }

        th {
            background: #0f172a;
            color: #f8fafc;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .slot-status {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-weight: 600;
        }

        .text-muted {
            color: var(--muted);
        }

        @media (max-width: 760px) {
            body {
                padding: 14px;
            }

            .panel {
                padding: 16px;
            }

            .heading h1 {
                font-size: 1.35rem;
            }

            .actions {
                width: 100%;
            }

            .btn {
                flex: 1;
            }
        }
    </style>
</head>
<body>
    <div class="page-wrap">
        <section class="panel">
            <div class="heading">
                <div>
                    <h1>Book Appointment</h1>
                    <p class="muted">Welcome, <?php echo $patientName; ?>. Pick a doctor and reserve an available slot.</p>
                </div>
                <div class="actions">
                    <a class="btn btn-outline" href="dashboard.php">Back to Dashboard</a>
                </div>
            </div>

            <?php if ($successMessage !== ''): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </section>

        <section class="panel">
            <h2 style="margin: 0; font-size: 1.25rem;">Doctors and Specialties</h2>
            <p class="muted" style="margin-top: 8px;">Green means at least one upcoming free slot. Red means no upcoming free slots.</p>

            <div class="doctor-grid">
                <?php if (!empty($doctorCards)): ?>
                    <?php foreach ($doctorCards as $doctor): ?>
                        <?php
                            $availableCount = (int)($doctor['available_slots'] ?? 0);
                            $upcomingCount = (int)($doctor['upcoming_slots'] ?? 0);
                            $isAvailable = $availableCount > 0;
                        ?>
                        <article class="doctor-card">
                            <h3><?php echo htmlspecialchars($doctor['fullname'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <span class="chip"><?php echo htmlspecialchars($doctor['specialization'], ENT_QUOTES, 'UTF-8'); ?></span>

                            <div class="status-line">
                                <span class="status-dot <?php echo $isAvailable ? 'available' : 'unavailable'; ?>"></span>
                                <span><?php echo $isAvailable ? 'Available' : 'Unavailable'; ?></span>
                            </div>

                            <div class="stats">
                                <span class="stat">Open Slots: <?php echo $availableCount; ?></span>
                                <span class="stat">Upcoming Slots: <?php echo $upcomingCount; ?></span>
                                <span class="stat">Total Slots: <?php echo (int)($doctor['total_slots'] ?? 0); ?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <article class="doctor-card">
                        <p class="text-muted" style="margin: 0;">No doctors found yet.</p>
                    </article>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel">
            <h2 style="margin: 0; font-size: 1.25rem;">Available Time Slots</h2>
            <p class="muted" style="margin-top: 8px;">Book only slots marked with a green status indicator.</p>

            <div class="slots-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 85px;">Slot ID</th>
                            <th>Doctor</th>
                            <th>Specialty</th>
                            <th style="width: 150px;">Date</th>
                            <th style="width: 120px;">Time</th>
                            <th style="width: 170px;">Status</th>
                            <th style="width: 160px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($slots)): ?>
                            <?php foreach ($slots as $slot): ?>
                                <?php
                                    $isUpcoming = (int)$slot['is_upcoming'] === 1;
                                    $isBooked = (int)$slot['is_booked'] === 1;
                                    $canBook = $isUpcoming && !$isBooked;
                                    $statusText = $canBook ? 'Available' : ($isUpcoming ? 'Booked' : 'Unavailable');
                                ?>
                                <tr>
                                    <td><?php echo (int)$slot['schedule_id']; ?></td>
                                    <td><?php echo htmlspecialchars($slot['fullname'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($slot['specialization'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($slot['available_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(substr($slot['available_time'], 0, 5), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <span class="slot-status">
                                            <span class="status-dot <?php echo $canBook ? 'available' : 'unavailable'; ?>"></span>
                                            <span><?php echo $statusText; ?></span>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($canBook): ?>
                                            <form method="POST" action="" style="margin: 0;">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['book_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="schedule_id" value="<?php echo (int)$slot['schedule_id']; ?>">
                                                <button type="submit" class="btn btn-brand" onclick="return confirm('Book this slot?');">Book Now</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">Not bookable</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-muted" style="text-align: center; padding: 18px;">No schedule slots available yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</body>
</html>
