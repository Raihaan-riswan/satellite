<?php
// satellites.php - Satellite Management & Real-time Orbital Inspector
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
  <title>OrbitTrack - Satellites Catalog</title>
  <link rel="stylesheet" href="asset/css/style.css">
  
  <!-- Leaflet CSS for Interactive 2D Map -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      background: #060913;
      color: #e2e8f0;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      min-height: 100vh;
    }

    .dashboard-container {
      display: flex;
      min-height: 100vh;
    }

    /* Sidebar Navigation */
    .sidebar {
      width: 240px;
      background: #0b1329;
      border-right: 1px solid rgba(255, 255, 255, 0.08);
      padding: 25px 20px;
      display: flex;
      flex-direction: column;
    }
    .brand {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 35px;
    }
    .brand-dot {
      width: 12px;
      height: 12px;
      background: #38bdf8;
      border-radius: 50%;
      box-shadow: 0 0 10px #38bdf8;
    }
    .brand h2 {
      font-size: 1.1rem;
      letter-spacing: 1px;
      color: #ffffff;
    }
    .sidebar nav a {
      display: block;
      padding: 12px 14px;
      color: #64748b;
      text-decoration: none;
      border-radius: 8px;
      font-size: 0.9rem;
      margin-bottom: 6px;
      transition: all 0.2s;
    }
    .sidebar nav a:hover, .sidebar nav a.active {
      background: rgba(56, 189, 248, 0.1);
      color: #38bdf8;
    }

    /* Main Area */
    .main-content {
      flex: 1;
      padding: 35px 45px;
      overflow-y: auto;
    }

    .top-bar {
      margin-bottom: 25px;
    }
    .top-bar h1 {
      font-size: 1.8rem;
      font-weight: 600;
      color: #ffffff;
    }
    .top-bar p {
      color: #64748b;
      font-size: 0.9rem;
      margin-top: 4px;
    }

    /* Control Bar */
    .controls-bar {
      display: flex;
      justify-content: space-between;
      gap: 15px;
      margin-bottom: 25px;
    }
    .search-box, .filter-select {
      padding: 10px 14px;
      background: #0d1424;
      border: 1px solid #1e293b;
      color: #ffffff;
      border-radius: 8px;
      font-size: 0.9rem;
    }
    .search-box:focus, .filter-select:focus {
      outline: none;
      border-color: #38bdf8;
    }
    .btn-add {
      background: linear-gradient(90deg, #38bdf8 0%, #6366f1 100%);
      color: #ffffff;
      padding: 10px 20px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
      font-size: 0.9rem;
      transition: opacity 0.2s;
    }
    .btn-add:hover { opacity: 0.9; }

    /* Table Styling */
    .sat-table {
      width: 100%;
      border-collapse: collapse;
      background: rgba(13, 20, 36, 0.75);
      border-radius: 12px;
      overflow: hidden;
      border: 1px solid rgba(255, 255, 255, 0.08);
      box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }
    .sat-table th, .sat-table td {
      padding: 14px 20px;
      text-align: left;
      border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }
    .sat-table th {
      background: rgba(15, 23, 42, 0.9);
      color: #64748b;
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .sat-row {
      cursor: pointer;
      transition: background 0.2s;
    }
    .sat-row:hover {
      background: rgba(56, 189, 248, 0.06);
    }

    .status-badge {
      padding: 4px 10px;
      border-radius: 12px;
      font-size: 0.75rem;
      font-weight: 600;
    }
    .status-active { background: rgba(52, 211, 153, 0.15); color: #34d399; }
    .status-inactive { background: rgba(248, 113, 113, 0.15); color: #f87171; }

    /* Modal Overlay & Details Inspector */
    .modal {
      display: none;
      position: fixed;
      top: 0; left: 0; width: 100%; height: 100%;
      background: rgba(4, 7, 15, 0.85);
      backdrop-filter: blur(8px);
      justify-content: center;
      align-items: center;
      z-index: 1000;
      padding: 20px;
    }

    .modal-content-lg {
      background: #0d1424;
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 16px;
      width: 100%;
      max-width: 920px;
      max-height: 90vh;
      overflow-y: auto;
      padding: 30px;
      box-shadow: 0 25px 50px rgba(0,0,0,0.6);
      position: relative;
    }

    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      padding-bottom: 15px;
      margin-bottom: 20px;
    }

    .modal-header h2 {
      font-size: 1.5rem;
      color: #ffffff;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .btn-close {
      background: transparent;
      border: none;
      color: #64748b;
      font-size: 1.5rem;
      cursor: pointer;
    }
    .btn-close:hover { color: #ffffff; }

    /* Grid Layout for Telemetry Cards */
    .info-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 16px;
      margin-bottom: 24px;
    }

    .info-card {
      background: #070b14;
      border: 1px solid #1e293b;
      border-radius: 10px;
      padding: 16px;
    }

    .info-card h4 {
      font-size: 0.8rem;
      text-transform: uppercase;
      color: #38bdf8;
      letter-spacing: 0.5px;
      margin-bottom: 12px;
    }

    .data-row {
      display: flex;
      justify-content: space-between;
      margin-bottom: 8px;
      font-size: 0.88rem;
    }
    .data-label { color: #64748b; }
    .data-val { color: #ffffff; font-weight: 600; }

    /* Map Box */
    #satMap {
      width: 100%;
      height: 250px;
      border-radius: 10px;
      border: 1px solid #1e293b;
      margin-bottom: 24px;
    }

    /* Pass Predictions Table */
    .pass-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
      font-size: 0.85rem;
    }
    .pass-table th, .pass-table td {
      padding: 10px;
      text-align: left;
      border-bottom: 1px solid #1e293b;
    }
    .pass-table th { color: #64748b; background: #070b14; }

    /* Code Snippet / TLE Block */
    .tle-block {
      background: #04070f;
      border: 1px solid #1e293b;
      border-radius: 8px;
      padding: 12px;
      font-family: monospace;
      font-size: 0.8rem;
      color: #38bdf8;
      white-space: pre-wrap;
      word-break: break-all;
    }
  </style>
</head>
<body>

  <div class="dashboard-container">
    
    <!-- Sidebar -->
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

    <!-- Main Content Area -->
    <main class="main-content">
      <div class="top-bar">
        <h1>Satellites Catalog</h1>
        <p>Click on any satellite row to inspect real-time positions, passes, and orbital data.</p>
      </div>

      <?php if ($message): ?><p style="color: #34d399; margin-bottom:15px;"><?= htmlspecialchars($message); ?></p><?php endif; ?>
      <?php if ($error): ?><p style="color: #f87171; margin-bottom:15px;"><?= htmlspecialchars($error); ?></p><?php endif; ?>

      <!-- Search & Filters -->
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
          <button type="submit" class="btn-add" style="background:#0d1424; border:1px solid #1e293b;">Filter</button>
        </form>

        <button class="btn-add" onclick="document.getElementById('addModal').style.display='flex'">+ Add Satellite</button>
      </div>

      <!-- Satellites Table -->
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
            <tr><td colspan="5" style="text-align:center; color:#64748b;">No satellites found in database.</td></tr>
          <?php else: ?>
            <?php foreach ($satellites as $sat): ?>
              <tr class="sat-row" onclick="openInspector(<?= htmlspecialchars(json_encode($sat)); ?>)">
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

  <!-- Detailed Inspector Popup Modal -->
  <div id="inspectorModal" class="modal">
    <div class="modal-content-lg">
      <div class="modal-header">
        <h2>
          <span id="modalSatName">ISS (ZARYA)</span>
          <span id="modalSatBadge" class="status-badge status-active">Active</span>
        </h2>
        <button class="btn-close" onclick="closeInspector()">&times;</button>
      </div>

      <!-- Live Map Viewport -->
      <div id="satMap"></div>

      <!-- Telemetry and Data Grid -->
      <div class="info-grid">
        
        <!-- Section 1: Live Positions -->
        <div class="info-card">
          <h4>1. Live Orbital Position</h4>
          <div class="data-row"><span class="data-label">Latitude:</span><span class="data-val" id="valLat">--</span></div>
          <div class="data-row"><span class="data-label">Longitude:</span><span class="data-val" id="valLng">--</span></div>
          <div class="data-row"><span class="data-label">Altitude:</span><span class="data-val" id="valAlt">418.2 km</span></div>
          <div class="data-row"><span class="data-label">Speed & Velocity:</span><span class="data-val" id="valSpeed">7.66 km/s</span></div>
          <div class="data-row"><span class="data-label">Coverage Radius:</span><span class="data-val" id="valFootprint">2,200 km</span></div>
        </div>

        <!-- Section 3: Metadata -->
        <div class="info-card">
          <h4>3. Metadata & Catalog Info</h4>
          <div class="data-row"><span class="data-label">NORAD ID:</span><span class="data-val" id="valNorad">--</span></div>
          <div class="data-row"><span class="data-label">Category:</span><span class="data-val" id="valCategory">--</span></div>
          <div class="data-row"><span class="data-label">Launch Date:</span><span class="data-val" id="valLaunch">1998-11-20</span></div>
          <div class="data-row"><span class="data-label">Owner / Country:</span><span class="data-val" id="valOwner">International</span></div>
          <div class="data-row"><span class="data-label">Orbital Period:</span><span class="data-val">92.68 mins</span></div>
        </div>

      </div>

      <!-- Section 2: Pass Predictions -->
      <div class="info-card" style="margin-bottom:24px;">
        <h4>2. Upcoming Pass Predictions (Your Location)</h4>
        <table class="pass-table">
          <thead>
            <tr>
              <th>AOS Time</th>
              <th>AOS Direction</th>
              <th>Max Elevation</th>
              <th>LOS Time & Direction</th>
              <th>Visibility</th>
            </tr>
          </thead>
          <tbody id="passTableBody">
            <tr>
              <td>Today, 19:42</td>
              <td>10° (N)</td>
              <td><strong>68° (High Pass)</strong></td>
              <td>19:48 / 135° (SE)</td>
              <td><span style="color:#34d399;">Visible (Night)</span></td>
            </tr>
            <tr>
              <td>Tomorrow, 05:15</td>
              <td>320° (NW)</td>
              <td>22° (Low Pass)</td>
              <td>05:19 / 80° (E)</td>
              <td><span style="color:#64748b;">Radio Only</span></td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Section 3 (Contd): TLE Data -->
      <div class="info-card">
        <h4>Two-Line Element (TLE) Data Format</h4>
        <div class="tle-block" id="valTle">
1 25544U 98067A   23274.52187500  .00016717  00000+0  30123-3 0  9993
2 25544  51.6416 288.1210 0004123 112.3100 247.8100 15.49812345417891
        </div>
      </div>

    </div>
  </div>

  <!-- Add Satellite Modal -->
  <div id="addModal" class="modal">
    <div class="modal-content-lg" style="max-width:480px;">
      <h3 style="color:#fff; margin-bottom:15px;">Add New Satellite</h3>
      <form method="POST">
        <input type="hidden" name="action" value="create">
        
        <div style="margin-bottom:12px;">
          <label style="display:block; font-size:0.85rem; color:#64748b; margin-bottom:4px;">Satellite Name</label>
          <input type="text" name="name" required placeholder="e.g. ISS (ZARYA)" class="search-box" style="width:100%;">
        </div>

        <div style="margin-bottom:12px;">
          <label style="display:block; font-size:0.85rem; color:#64748b; margin-bottom:4px;">NORAD Catalog ID</label>
          <input type="number" name="norad_id" required placeholder="e.g. 25544" class="search-box" style="width:100%;">
        </div>

        <div style="margin-bottom:12px;">
          <label style="display:block; font-size:0.85rem; color:#64748b; margin-bottom:4px;">Category</label>
          <select name="category" class="filter-select" style="width:100%;">
            <option value="Space Station">Space Station</option>
            <option value="Weather">Weather</option>
            <option value="Communication">Communication</option>
            <option value="Navigation">Navigation</option>
          </select>
        </div>

        <div style="margin-bottom:12px;">
          <label style="display:block; font-size:0.85rem; color:#64748b; margin-bottom:4px;">Status</label>
          <select name="status" class="filter-select" style="width:100%;">
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
          </select>
        </div>

        <div style="margin-bottom:12px;">
          <label style="display:block; font-size:0.85rem; color:#64748b; margin-bottom:4px;">TLE Line 1</label>
          <input type="text" name="tle_line1" placeholder="1 25544U..." class="search-box" style="width:100%;">
        </div>

        <div style="margin-bottom:16px;">
          <label style="display:block; font-size:0.85rem; color:#64748b; margin-bottom:4px;">TLE Line 2</label>
          <input type="text" name="tle_line2" placeholder="2 25544..." class="search-box" style="width:100%;">
        </div>

        <div style="display:flex; justify-content:flex-end; gap:10px;">
          <button type="button" onclick="document.getElementById('addModal').style.display='none'" style="padding:10px 16px; background:transparent; color:#fff; border:none; cursor:pointer;">Cancel</button>
          <button type="submit" class="btn-add">Save Satellite</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Leaflet Map JS -->
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

  <script>
    let map, marker, circle;

    function initMap() {
      if (!map) {
        map = L.map('satMap').setView([0, 0], 2);
        
        // Esri World Dark Gray Canvas (Free, no API key watermark)
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Dark_Gray_Base/MapServer/tile/{z}/{y}/{x}', {
          maxZoom: 16,
          attribution: 'Tiles &copy; Esri &mdash; Esri, DeLorme, NAVTEQ'
        }).addTo(map);
      }
    }

    function openInspector(sat) {
      document.getElementById('modalSatName').innerText = sat.name;
      document.getElementById('modalSatBadge').innerText = sat.status;
      document.getElementById('modalSatBadge').className = 'status-badge ' + (sat.status === 'Active' ? 'status-active' : 'status-inactive');
      
      document.getElementById('valNorad').innerText = sat.norad_id;
      document.getElementById('valCategory').innerText = sat.category;

      // Populate TLE lines or default mockup
      let t1 = sat.tle_line1 ? sat.tle_line1 : '1 ' + sat.norad_id + 'U 98067A   23274.52187500  .00016717  00000+0  30123-3 0  9993';
      let t2 = sat.tle_line2 ? sat.tle_line2 : '2 ' + sat.norad_id + '  51.6416 288.1210 0004123 112.3100 247.8100 15.49812345417891';
      document.getElementById('valTle').innerText = t1 + '\n' + t2;

      // Generate random simulated live coordinates for visual demonstration
      let lat = (Math.random() * 120 - 60).toFixed(4);
      let lng = (Math.random() * 360 - 180).toFixed(4);

      document.getElementById('valLat').innerText = lat + '°';
      document.getElementById('valLng').innerText = lng + '°';

      // Display Modal
      document.getElementById('inspectorModal').style.display = 'flex';

      // Initialize map & refresh rendering layout
      setTimeout(() => {
        initMap();
        map.invalidateSize();
        map.setView([lat, lng], 3);

        if (marker) map.removeLayer(marker);
        if (circle) map.removeLayer(circle);

        marker = L.marker([lat, lng]).addTo(map).bindPopup('<b>' + sat.name + '</b><br>Live Position').openPopup();
        circle = L.circle([lat, lng], {
          color: '#38bdf8',
          fillColor: '#38bdf8',
          fillOpacity: 0.15,
          radius: 1200000
        }).addTo(map);
      }, 200);
    }

    function closeInspector() {
      document.getElementById('inspectorModal').style.display = 'none';
    }
  </script>

</body>
</html>