<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/admin_auth.php';

$summary = [
    'today_appointments' => 0,
    'upcoming_booked' => 0,
    'completed' => 0,
    'cancelled' => 0
];

$summaryQueries = [
    'today_appointments' => 'SELECT COUNT(*) AS total FROM Appointment WHERE appointment_date = CURDATE()',
    'upcoming_booked' => "SELECT COUNT(*) AS total FROM Appointment WHERE status = 'Booked' AND appointment_date >= CURDATE()",
    'completed' => "SELECT COUNT(*) AS total FROM Appointment WHERE status = 'Completed'",
    'cancelled' => "SELECT COUNT(*) AS total FROM Appointment WHERE status = 'Cancelled'"
];

foreach ($summaryQueries as $key => $sql) {
    $result = mysqli_query($conn, $sql);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $summary[$key] = (int)($row['total'] ?? 0);
    }
}

$doctorLoad = [];
$doctorLoadSql = "SELECT d.fullname, d.specialization,
                         SUM(CASE WHEN a.status = 'Booked' THEN 1 ELSE 0 END) AS booked_count,
                         SUM(CASE WHEN a.status = 'Completed' THEN 1 ELSE 0 END) AS completed_count,
                         SUM(CASE WHEN a.status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled_count,
                         COUNT(a.appointment_id) AS total_count
                  FROM Doctor d
                  LEFT JOIN Schedule s ON s.doctor_id = d.doctor_id
                  LEFT JOIN Appointment a ON a.schedule_id = s.schedule_id
                  GROUP BY d.doctor_id, d.fullname, d.specialization
                  ORDER BY total_count DESC, d.fullname ASC";

$doctorLoadResult = mysqli_query($conn, $doctorLoadSql);
if ($doctorLoadResult) {
    while ($row = mysqli_fetch_assoc($doctorLoadResult)) {
        $doctorLoad[] = $row;
    }
}

$dailyTrend = [];
$dailyTrendSql = "SELECT appointment_date,
                         SUM(CASE WHEN status = 'Booked' THEN 1 ELSE 0 END) AS booked_count,
                         SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed_count,
                         SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled_count,
                         COUNT(*) AS total
                  FROM Appointment
                  WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                  GROUP BY appointment_date
                  ORDER BY appointment_date DESC";

$dailyTrendResult = mysqli_query($conn, $dailyTrendSql);
if ($dailyTrendResult) {
    while ($row = mysqli_fetch_assoc($dailyTrendResult)) {
        $dailyTrend[] = $row;
    }
}

$trendLabels = [];
$trendBooked = [];
$trendCompleted = [];
$trendCancelled = [];

$dailyTrendAsc = array_reverse($dailyTrend);
foreach ($dailyTrendAsc as $row) {
    $trendLabels[] = $row['appointment_date'];
    $trendBooked[] = (int)$row['booked_count'];
    $trendCompleted[] = (int)$row['completed_count'];
    $trendCancelled[] = (int)$row['cancelled_count'];
}

$doctorNames = [];
$doctorTotals = [];
$topDoctorLoad = array_slice($doctorLoad, 0, 8);
foreach ($topDoctorLoad as $row) {
    $doctorNames[] = $row['fullname'];
    $doctorTotals[] = (int)$row['total_count'];
}

$statusSummaryLabels = ['Booked', 'Completed', 'Cancelled'];
$statusSummaryValues = [
    (int)$summary['upcoming_booked'],
    (int)$summary['completed'],
    (int)$summary['cancelled']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/refined-theme.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <style>
        body {
            background:
                linear-gradient(135deg, rgba(243, 248, 255, 0.56), rgba(231, 248, 238, 0.56)),
                url('assets/images/admin-workspace-bg.svg') center/cover no-repeat fixed,
                linear-gradient(135deg, #f3f8ff, #e7f8ee);
            background-blend-mode: normal;
            min-height: 100vh;
        }
        .page-wrap { max-width: 1240px; margin: 30px auto; padding: 0 16px; }
        .panel { background: #fff; border-radius: 14px; box-shadow: 0 12px 30px rgba(0,0,0,0.08); padding: 24px; margin-bottom: 16px; }
        .title-row { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:14px; flex-wrap:wrap; }
        .kpi-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap:12px; }
        .kpi { border:1px solid #dbe7ef; border-radius:12px; padding:14px; }
        .kpi h4 { margin:0; font-size:0.9rem; color:#334155; }
        .kpi .value { margin-top:8px; font-size:1.5rem; font-weight:800; color:#0f766e; }
        .chart-grid { display:grid; grid-template-columns: 2fr 1fr; gap:14px; }
        .chart-box { border:1px solid #dbe7ef; border-radius:12px; padding:12px; background:#fff; }
        .chart-title { font-size:0.95rem; font-weight:700; color:#1e293b; margin:0 0 10px; }
        .chart-canvas-wrap { position: relative; min-height: 260px; }

        @media (max-width: 980px) {
            .chart-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="page-wrap">
        <div class="panel">
            <div class="title-row">
                <h2 class="mb-0">Admin - Reports</h2>
                <?php
                    $adminNavMode = 'buttons';
                    $adminActivePage = 'reports';
                    require __DIR__ . '/../../includes/partials/admin_nav.php';
                ?>
            </div>
            <div class="section-hero">
                <p class="page-kicker">Insights</p>
                <h3 class="page-title">Clinic Performance Snapshot</h3>
                <p class="page-subtitle">Track trends, status mix, and doctor workload from one unified reporting view.</p>
            </div>
            <div class="kpi-grid">
                <article class="kpi"><h4>Today Appointments</h4><div class="value"><?php echo $summary['today_appointments']; ?></div></article>
                <article class="kpi"><h4>Upcoming Booked</h4><div class="value"><?php echo $summary['upcoming_booked']; ?></div></article>
                <article class="kpi"><h4>Total Completed</h4><div class="value"><?php echo $summary['completed']; ?></div></article>
                <article class="kpi"><h4>Total Cancelled</h4><div class="value"><?php echo $summary['cancelled']; ?></div></article>
            </div>
        </div>

        <div class="panel">
            <h4 class="mb-3">Visual Insights</h4>
            <div class="chart-grid">
                <div class="chart-box">
                    <p class="chart-title">30 Day Appointment Trend</p>
                    <div class="chart-canvas-wrap">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
                <div class="chart-box">
                    <p class="chart-title">Status Distribution</p>
                    <div class="chart-canvas-wrap">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="chart-box mt-3">
                <p class="chart-title">Top Doctors by Appointment Volume</p>
                <div class="chart-canvas-wrap">
                    <canvas id="doctorLoadChart"></canvas>
                </div>
            </div>
        </div>

        <div class="panel">
            <h4 class="mb-3">Doctor Workload Summary</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Doctor</th>
                            <th>Specialization</th>
                            <th style="width:110px;">Booked</th>
                            <th style="width:110px;">Completed</th>
                            <th style="width:110px;">Cancelled</th>
                            <th style="width:110px;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($doctorLoad)): ?>
                            <?php foreach ($doctorLoad as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['fullname'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($row['specialization'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo (int)$row['booked_count']; ?></td>
                                    <td><?php echo (int)$row['completed_count']; ?></td>
                                    <td><?php echo (int)$row['cancelled_count']; ?></td>
                                    <td><?php echo (int)$row['total_count']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <div class="empty-icon">R</div>
                                        <h4>No report data available</h4>
                                        <p>Appointments and status updates will populate this report automatically.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel">
            <h4 class="mb-3">Last 30 Days Appointment Trend</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th style="width:140px;">Date</th>
                            <th style="width:110px;">Booked</th>
                            <th style="width:110px;">Completed</th>
                            <th style="width:110px;">Cancelled</th>
                            <th style="width:110px;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($dailyTrend)): ?>
                            <?php foreach ($dailyTrend as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['appointment_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo (int)$row['booked_count']; ?></td>
                                    <td><?php echo (int)$row['completed_count']; ?></td>
                                    <td><?php echo (int)$row['cancelled_count']; ?></td>
                                    <td><?php echo (int)$row['total']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <div class="empty-icon">T</div>
                                        <h4>No recent appointments</h4>
                                        <p>Once activity starts, the last 30 day trend appears here.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const trendLabels = <?php echo json_encode($trendLabels); ?>;
        const trendBooked = <?php echo json_encode($trendBooked); ?>;
        const trendCompleted = <?php echo json_encode($trendCompleted); ?>;
        const trendCancelled = <?php echo json_encode($trendCancelled); ?>;

        const statusLabels = <?php echo json_encode($statusSummaryLabels); ?>;
        const statusValues = <?php echo json_encode($statusSummaryValues); ?>;

        const doctorNames = <?php echo json_encode($doctorNames); ?>;
        const doctorTotals = <?php echo json_encode($doctorTotals); ?>;

        if (document.getElementById('trendChart')) {
            new Chart(document.getElementById('trendChart'), {
                type: 'line',
                data: {
                    labels: trendLabels,
                    datasets: [
                        {
                            label: 'Booked',
                            data: trendBooked,
                            borderColor: '#16a34a',
                            backgroundColor: 'rgba(22, 163, 74, 0.16)',
                            tension: 0.3,
                            fill: true
                        },
                        {
                            label: 'Completed',
                            data: trendCompleted,
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37, 99, 235, 0.14)',
                            tension: 0.3,
                            fill: true
                        },
                        {
                            label: 'Cancelled',
                            data: trendCancelled,
                            borderColor: '#dc2626',
                            backgroundColor: 'rgba(220, 38, 38, 0.14)',
                            tension: 0.3,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        if (document.getElementById('statusChart')) {
            new Chart(document.getElementById('statusChart'), {
                type: 'doughnut',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        data: statusValues,
                        backgroundColor: ['#16a34a', '#2563eb', '#dc2626']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        if (document.getElementById('doctorLoadChart')) {
            new Chart(document.getElementById('doctorLoadChart'), {
                type: 'bar',
                data: {
                    labels: doctorNames,
                    datasets: [{
                        label: 'Total Appointments',
                        data: doctorTotals,
                        backgroundColor: 'rgba(15, 118, 110, 0.75)',
                        borderRadius: 8
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>
