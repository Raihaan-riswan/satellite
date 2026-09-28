<?php
// satellites.php - Satellite Management
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/db.php';

$message = '';
$error = '';

// --- Handle Add / Edit Satellite Forms ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action       = $_POST['action'] ?? '';
    $sat_name     = trim($_POST['name'] ?? '');
    $norad_id     = trim($_POST['norad_id'] ?? '');
    $category     = $_POST['category'] ?? 'General';
    $status       = $_POST['status'] ?? 'Active';
    $tle_line1    = trim($_POST['tle_line1'] ?? '');
    $tle_line2    = trim($_POST['tle_line2'] ?? '');

    if ($action === 'create') {
        if (!empty($sat_name) && !empty($norad_id)) {
            $stmt = $pdo->prepare("
                INSERT INTO satellites (name, norad_id, category, status, tle_line1, tle_line2) 
                VALUES (:name, :norad_id, :category, :status, :tle1, :tle2)
            ");
            if ($stmt->execute([
                ':name' => $sat_name,
                ':norad_id' => $norad_id,
                ':category' => $category,
                ':status' => $status,
                ':tle1' => $tle_line1,
                ':tle2' => $tle_line2
            ])) {
                $new_id = $pdo->lastInsertId();
                // Log action in audit_logs
                $log_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, satellite_id, action) VALUES (?, ?, 'CREATED')");
                $log_stmt->execute([$_SESSION['user_id'], $new_id]);

                $message = "Satellite added successfully!";
            }
        } else {
            $error = "Satellite Name and NORAD ID are required.";
        }
    }
}

// --- Fetch Satellites with Search & Category Filter ---
$search   = trim($_GET['search'] ?? '');
$filter_cat = trim($_GET['category'] ?? '');

$query = "SELECT * FROM satellites WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (name LIKE :search OR norad_id LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($filter_cat)) {
    $query .= " AND category = :cat";
    $params[':cat'] = $filter_cat;
}

$query .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$satellites = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>OrbitTrack - Satellites</title>
  <link rel="stylesheet" href="asset/css/style.css">
  <style>
    .controls-bar {
      display: flex;
      justify-content: space-between;
      gap: 15px;
      margin-bottom: 20px;
    }
    .search-box input, .filter-select {
      padding: 8px 12px;
      background: var(--card-navy);
      border: 1px solid var(--border-blue);
      color: var(--text-primary);
      border-radius: 6px;
    }
    .btn-add {
      background: var(--accent-primary);
      color: #fff;
      padding: 8px 16px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
    }
    .sat-table {
      width: 100%;
      border-collapse: collapse;
      background: var(--card-navy);
      border-radius: 8px;
      overflow: hidden;
      border: 1px solid var(--border-blue);
    }
    .sat-table th, .sat-table td {
      padding: 12px 16px;
      text-align: left;
      border-bottom: 1px solid var(--border-blue);
    }
    .sat-table th {
      background: rgba(35, 51, 85, 0.5);
      color: var(--text-muted);
      font-size: 0.85rem;
      text-transform: uppercase;
    }
    .status-badge {
      padding: 2px 8px;
      border-radius: 12px;
      font-size: 0.75rem;
      font-weight: bold;
    }
    .status-active { background: rgba(52, 211, 153, 0.2); color: var(--status-active); }
    .status-inactive { background: rgba(248, 113, 113, 0.2); color: var(--status-danger); }
    
    /* Modal Form Styling */
    .modal {
      display: none;
      position: fixed;
      top: 0; left: 0; width: 100%; height: 100%;
      background: rgba(0,0,0,0.7);
      justify-content: center;
      align-items: center;
    }
    .modal-content {
      background: var(--card-navy);
      padding: 25px;
      border-radius: 8px;
      width: 450px;
      border: 1px solid var(--border-blue);
    }
    .form-group { margin-bottom: 12px; }
    .form-group label { display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px; }
    .form-group input, .form-group select, .form-group textarea {
      width: 100%; padding: 8px; background: var(--bg-navy); border: 1px solid var(--border-blue); color: #fff; border-radius: 4px;
    }
  </style>
</head>
<body>

  <div class="dashboard-container">
    
    <aside class="sidebar">
      <div class="brand">
        <div class="brand-dot"></div>
        <h2>ORBITTRACK</h2>
      </div>
      <nav>
        <a href="index.php">Dashboard</a>
        <a href="satellites.php" class="active">Satellites</a>
        <a href="live_map.php">Live Map</a>
        <a href="passes.php">Pass Predictions</a>
        <?php if ($_SESSION['role'] === 'admin'): ?>
          <a href="users_manage.php">User Management</a>
          <a href="audit_log.php">Audit Log</a>
        <?php endif; ?>
        <a href="profile.php">My Profile</a>
        <a href="logout.php">Logout</a>
      </nav>
    </aside>

    <main class="main-content">
      <div class="top-bar">
        <div>
          <h1>Satellites Management</h1>
          <p>Browse, search, and edit satellite TLE data</p>
        </div>
      </div>

      <?php if ($message): ?><p style="color: var(--status-active); margin-bottom:15px;"><?= $message; ?></p><?php endif; ?>
      <?php if ($error): ?><p style="color: var(--status-danger); margin-bottom:15px;"><?= $error; ?></p><?php endif; ?>

      <!-- Search & Filters Bar -->
      <div class="controls-bar">
        <form method="GET" style="display:flex; gap:10px;">
          <input type="text" name="search" placeholder="Search by name or NORAD..." value="<?= htmlspecialchars($search); ?>" class="search-box">
          <select name="category" class="filter-select" onchange="this.form.submit()">
            <option value="">All Categories</option>
            <option value="Space Station" <?= $filter_cat === 'Space Station' ? 'selected' : ''; ?>>Space Station</option>
            <option value="Weather" <?= $filter_cat === 'Weather' ? 'selected' : ''; ?>>Weather</option>
            <option value="Communication" <?= $filter_cat === 'Communication' ? 'selected' : ''; ?>>Communication</option>
            <option value="Navigation" <?= $filter_cat === 'Navigation' ? 'selected' : ''; ?>>Navigation</option>
          </select>
          <button type="submit" class="btn-add" style="background:var(--card-navy); border:1px solid var(--border-blue);">Filter</button>
        </form>

        <button class="btn-add" onclick="document.getElementById('addModal').style.display='flex'">+ Add Satellite</button>
      </div>

      <!-- Table View -->
      <table class="sat-table">
        <thead>
          <tr>
            <th>NORAD ID</th>
            <th>Name</th>
            <th>Category</th>
            <th>Status</th>
            <th>Added On</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($satellites)): ?>
            <tr><td colspan="5">No satellites found.</td></tr>
          <?php else: ?>
            <?php foreach ($satellites as $sat): ?>
              <tr>
                <td><strong><?= htmlspecialchars($sat['norad_id']); ?></strong></td>
                <td><?= htmlspecialchars($sat['name']); ?></td>
                <td><?= htmlspecialchars($sat['category']); ?></td>
                <td>
                  <span class="status-badge <?= $sat['status'] === 'Active' ? 'status-active' : 'status-inactive'; ?>">
                    <?= htmlspecialchars($sat['status']); ?>
                  </span>
                </td>
                <td><?= date('Y-m-d', strtotime($sat['created_at'])); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </main>
  </div>

  <!-- Add Satellite Modal -->
  <div id="addModal" class="modal">
    <div class="modal-content">
      <h3>Add New Satellite</h3>
      <form method="POST" style="margin-top:15px;">
        <input type="hidden" name="action" value="create">
        
        <div class="form-group">
          <label>Satellite Name</label>
          <input type="text" name="name" required placeholder="e.g. ISS (ZARYA)">
        </div>

        <div class="form-group">
          <label>NORAD Catalog ID</label>
          <input type="number" name="norad_id" required placeholder="e.g. 25544">
        </div>

        <div class="form-group">
          <label>Category</label>
          <select name="category">
            <option value="Space Station">Space Station</option>
            <option value="Weather">Weather</option>
            <option value="Communication">Communication</option>
            <option value="Navigation">Navigation</option>
          </select>
        </div>

        <div class="form-group">
          <label>Status</label>
          <select name="status">
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
          </select>
        </div>

        <div class="form-group">
          <label>TLE Line 1</label>
          <input type="text" name="tle_line1" placeholder="1 25544U...">
        </div>

        <div class="form-group">
          <label>TLE Line 2</label>
          <input type="text" name="tle_line2" placeholder="2 25544...">
        </div>

        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
          <button type="button" onclick="document.getElementById('addModal').style.display='none'" style="padding:8px 16px; background:transparent; color:#fff; border:none; cursor:pointer;">Cancel</button>
          <button type="submit" class="btn-add">Save Satellite</button>
        </div>
      </form>
    </div>
  </div>

</body>
</html>