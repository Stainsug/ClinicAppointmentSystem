<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/admin_auth.php';

if (empty($_SESSION['admin_doctor_csrf_token'])) {
    $_SESSION['admin_doctor_csrf_token'] = bin2hex(random_bytes(32));
}

$success = '';
$error = '';

if (isset($_GET['success'])) {
    if ($_GET['success'] === 'added') {
        $success = 'Doctor added successfully.';
    } elseif ($_GET['success'] === 'updated') {
        $success = 'Doctor updated successfully.';
    } elseif ($_GET['success'] === 'deleted') {
        $success = 'Doctor deleted successfully.';
    }
}

if (isset($_GET['error']) && $_GET['error'] === '1') {
    $error = 'An error occurred while processing your request.';
}

$search = trim($_GET['q'] ?? '');
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) {
    $currentPage = 1;
}

$perPage = 10;
$offset = ($currentPage - 1) * $perPage;

$totalRows = 0;
if ($search !== '') {
    $searchLike = '%' . $search . '%';
    $countSql = 'SELECT COUNT(*) AS total FROM Doctor WHERE fullname LIKE ? OR specialization LIKE ? OR email LIKE ?';
    $countStmt = mysqli_prepare($conn, $countSql);
    if ($countStmt) {
        mysqli_stmt_bind_param($countStmt, 'sss', $searchLike, $searchLike, $searchLike);
        mysqli_stmt_execute($countStmt);
        $countResult = mysqli_stmt_get_result($countStmt);
        $countRow = mysqli_fetch_assoc($countResult);
        $totalRows = (int)($countRow['total'] ?? 0);
        mysqli_stmt_close($countStmt);
    }
} else {
    $countSql = 'SELECT COUNT(*) AS total FROM Doctor';
    $countResult = mysqli_query($conn, $countSql);
    if ($countResult) {
        $countRow = mysqli_fetch_assoc($countResult);
        $totalRows = (int)($countRow['total'] ?? 0);
    }
}

$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
    $offset = ($currentPage - 1) * $perPage;
}

$doctors = [];
if ($search !== '') {
    $searchLike = '%' . $search . '%';
    $sql = 'SELECT doctor_id, fullname, specialization, email FROM Doctor
            WHERE fullname LIKE ? OR specialization LIKE ? OR email LIKE ?
            ORDER BY doctor_id DESC
            LIMIT ? OFFSET ?';
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'sssii', $searchLike, $searchLike, $searchLike, $perPage, $offset);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $doctors[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
} else {
    $sql = 'SELECT doctor_id, fullname, specialization, email FROM Doctor ORDER BY doctor_id DESC LIMIT ? OFFSET ?';
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $perPage, $offset);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $doctors[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
}

$searchValue = htmlspecialchars($search, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Doctor Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/refined-theme.css">
    <style>
        body {
            background:
                linear-gradient(135deg, rgba(243, 248, 255, 0.56), rgba(231, 248, 238, 0.56)),
                url('assets/images/admin-workspace-bg.svg') center/cover no-repeat fixed,
                linear-gradient(135deg, #f3f8ff, #e7f8ee);
            background-blend-mode: normal;
            min-height: 100vh;
        }
        .page-wrap { max-width: 1120px; margin: 30px auto; padding: 0 16px; }
        .panel { background: #fff; border-radius: 14px; box-shadow: 0 12px 30px rgba(0,0,0,0.08); padding: 24px; }
        .title-row { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:16px; flex-wrap:wrap; }
    </style>
</head>
<body>
    <div class="page-wrap">
        <div class="panel">
            <div class="title-row">
                <h2 class="mb-0">Admin - Manage Doctors</h2>
                <?php
                    $adminNavMode = 'buttons';
                    $adminActivePage = 'doctors';
                    require __DIR__ . '/../../includes/partials/admin_nav.php';
                ?>
            </div>

            <div class="section-hero">
                <p class="page-kicker">Directory</p>
                <h3 class="page-title"><span class="hero-chip">D</span>Doctor Registry</h3>
                <p class="page-subtitle">Maintain doctor profiles and keep specialties searchable for appointment matching.</p>
            </div>

            <div class="mb-3">
                <a href="admin_doctor_add.php" class="btn btn-success">Add Doctor</a>
            </div>

            <form method="GET" action="" class="d-flex gap-2 align-items-center flex-wrap mb-3">
                <input type="text" class="form-control" style="max-width: 320px;" name="q" value="<?php echo $searchValue; ?>" placeholder="Search by name, specialization, email">
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="admin_doctors.php" class="btn btn-outline-secondary">Reset</a>
            </form>

            <?php if ($success !== ''): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 90px;">ID</th>
                            <th>Full Name</th>
                            <th>Specialization</th>
                            <th>Email</th>
                            <th style="width: 190px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($doctors)): ?>
                            <?php foreach ($doctors as $doctor): ?>
                                <tr>
                                    <td><?php echo (int)$doctor['doctor_id']; ?></td>
                                    <td><?php echo htmlspecialchars($doctor['fullname'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($doctor['specialization'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($doctor['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <a class="btn btn-sm btn-primary" href="admin_doctor_edit.php?id=<?php echo (int)$doctor['doctor_id']; ?>">Edit</a>
                                        <form method="POST" action="admin_doctor_delete.php" class="d-inline m-0" onsubmit="return confirm('Delete this doctor? This will also remove linked schedules and appointments.');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_doctor_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="id" value="<?php echo (int)$doctor['doctor_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <div class="empty-icon">+</div>
                                        <h4>No doctors found</h4>
                                        <p>Add a doctor profile to start building appointment schedules.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="mt-3">
                    <ul class="pagination mb-0">
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <li class="page-item <?php echo $p === $currentPage ? 'active' : ''; ?>">
                                <a class="page-link" href="admin_doctors.php?page=<?php echo $p; ?>&q=<?php echo urlencode($search); ?>"><?php echo $p; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
