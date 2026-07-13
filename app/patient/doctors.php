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

$sql = 'SELECT doctor_id, fullname, specialization, email FROM Doctor ORDER BY doctor_id DESC';
$result = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Doctors</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/refined-theme.css">
    <style>
        body {
            background:
                linear-gradient(135deg, rgba(243, 248, 255, 0.56), rgba(231, 248, 238, 0.56)),
                url('assets/images/patient-workspace-bg.svg') center/cover no-repeat fixed,
                linear-gradient(135deg, #f3f8ff, #e7f8ee);
            background-blend-mode: normal;
            min-height: 100vh;
        }
        .page-wrap {
            max-width: 1100px;
            margin: 32px auto;
            padding: 0 16px;
        }
        .panel {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.08);
            padding: 24px;
        }
        .title-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
    <div class="page-wrap">
        <div class="panel">
            <div class="title-row">
                <h2 class="mb-0">Available Doctors</h2>
                <div class="d-flex gap-2">
                    <a href="dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
                </div>
            </div>

            <div class="section-hero">
                <p class="page-kicker">Directory</p>
                <h3 class="page-title">Find the Right Specialist</h3>
                <p class="page-subtitle">Review doctor profiles before choosing a timeslot on the booking page.</p>
            </div>

            <p class="text-muted">Browse doctors and their specialties before booking an appointment.</p>

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 90px;">ID</th>
                            <th>Full Name</th>
                            <th>Specialization</th>
                            <th>Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && mysqli_num_rows($result) > 0): ?>
                            <?php while ($doctor = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><?php echo (int)$doctor['doctor_id']; ?></td>
                                    <td><?php echo htmlspecialchars($doctor['fullname'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($doctor['specialization'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($doctor['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state">
                                        <div class="empty-icon">D</div>
                                        <h4>No doctors listed yet</h4>
                                        <p>The clinic team can add doctors soon. Check back later.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
