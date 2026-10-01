<?php
// passes.php - Satellite Pass Predictions
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/db.php';

$observer_lat = $_GET['lat'] ?? '6.9271';
$observer_lng = $_GET['lng'] ?? '79.8612';
$sat_id       = $_GET['sat_id'] ?? '';

$sat_stmt = $pdo->query("SELECT id, name, norad_id FROM satellites WHERE status = 'Active' ORDER BY name ASC");
$satellites = $sat_stmt->fetchAll();

$api_key = 'YOUR_N2YO_API_KEY'; 
$passes = [];

if (!empty($sat_id)) {
    $selected_sat = null;
    foreach ($satellites as $s) {
        if ($s['id'] == $sat_id) {
            $selected_sat = $s;
            break;
        }
    }

    if ($selected_sat && $api_key !== 'YOUR_N2YO_API_KEY') {
        $apiUrl = "https://api.n2yo.com/rest/v1/satellite/visualpasses/{$selected_sat['norad_id']}/{$observer_lat}/{$observer_lng}/0/10/300/&apiKey={$api_key}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['passes'])) {
                $passes = $data['passes'];
            }
        }
    } else {
        $passes = [
            [
                'startUTC' => time() + 3600,
                'maxEl' => 45,
                'startAzCompass' => 'NW',
                'maxAzCompass' => 'NE',
                'endAzCompass' => 'SE',
                'duration' => 380
            ],
            [
                'startUTC' => time() + 18000,
                'maxEl' => 72,
                'startAzCompass' => 'W',
                'maxAzCompass' => 'OVERHEAD',
                'endAzCompass' => 'E',
                'duration' => 540
            ]
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>OrbitTrack - Pass Predictions</title>
  <link rel="stylesheet" href="asset/css/style.css">
  <style>
    :root {
      --bg-space: #070a12;
      --card-navy: #0e1726;
      --border-blue: #1e293b;
      --text-primary: #f8fafc;
      --text-muted: #94a3b8;
      --accent-cyan: #38bdf8;
    }
    body {
      background-color: var(--bg-space);
      color: var(--text-primary);
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      margin: 0;
    }
    .dashboard-container { display: flex; min-height: 100vh; }
    .sidebar {
      width: 240px;
      background: #0b1120;
      border-right: 1px solid var(--border-blue);
      padding: 24px;
    }
    .brand { display: flex; align-items: center; gap: 10px; margin-bottom: 30px; }
    .brand-dot { width: 12px; height: 12px; background: var(--accent-cyan); border-radius: 50%; box-shadow: 0 0 10px var(--accent-cyan); }
    .brand h2 { font-size: 1.1rem; letter-spacing: 1.5px; margin: 0; color: #fff; }
    .sidebar nav a {
      display: block;
      padding: 12px 16px;
      color: var(--text-muted);
      text-decoration: none;
      border-radius: 8px;
      margin-bottom: 6px;
      font-size: 0.9rem;
      transition: all 0.2s;
    }
    .sidebar nav a:hover, .sidebar nav a.active {
      background: var(--border-blue);
      color: var(--accent-cyan);
    }
    .main-content { flex: 1; padding: 32px; overflow-y: auto; }
    .top-bar { margin-bottom: 24px; }
    .top-bar h1 { font-size: 1.6rem; margin: 0 0 6px 0; font-weight: 600; }
    .top-bar p { color: var(--text-muted); margin: 0; font-size: 0.88rem; }
    
    .filter-card {
      background: var(--card-navy);
      border: 1px solid var(--border-blue);
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 24px;
    }
    .form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
      align-items: end;
    }
    .form-group label {
      display: block;
      font-size: 0.82rem;
      color: var(--text-muted);
      margin-bottom: 8px;
    }
    .form-group input, .form-group select {
      width: 100%;
      padding: 10px 14px;
      background: #0b1120;
      border: 1px solid var(--border-blue);
      color: var(--text-primary);
      border-radius: 8px;
      font-size: 0.9rem;
      box-sizing: border-box;
    }
    .btn-predict {
      padding: 10px 20px;
      background: var(--accent-cyan);
      color: #0b1120;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      width: 100%;
      font-size: 0.9rem;
    }
    .btn-predict:hover { background: #7dd3fc; }
    
    .passes-table {
      width: 100%;
      border-collapse: collapse;
      background: var(--card-navy);
      border-radius: 12px;
      overflow: hidden;
      border: 1px solid var(--border-blue);
    }
    .passes-table th, .passes-table td {
      padding: 14px 16px;
      text-align: left;
      border-bottom: 1px solid var(--border-blue);
      font-size: 0.88rem;
    }
    .passes-table th {
      background: rgba(15, 23, 42, 0.6);
      color: var(--text-muted);
      font-size: 0.78rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
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
        <a href="satellites.php">Satellites</a>
        <a href="live_map.php">Live Map</a>
        <a href="passes.php" class="active">Pass Predictions</a>
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
        <h1>Pass Predictions</h1>
        <p>Calculate visible satellite passes over your observation coordinates</p>
      </div>

      <div class="filter-card">
        <form method="GET" class="form-grid">
          <div class="form-group">
            <label>Select Satellite</label>
            <select name="sat_id" required>
              <option value="">-- Choose Satellite --</option>
              <?php foreach ($satellites as $s): ?>
                <option value="<?= $s['id']; ?>" <?= $sat_id == $s['id'] ? 'selected' : ''; ?>>
                  <?= htmlspecialchars($s['name']); ?> (NORAD: <?= $s['norad_id']; ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label>Observer Latitude</label>
            <input type="text" name="lat" value="<?= htmlspecialchars($observer_lat); ?>" required>
          </div>

          <div class="form-group">
            <label>Observer Longitude</label>
            <input type="text" name="lng" value="<?= htmlspecialchars($observer_lng); ?>" required>
          </div>

          <div>
            <button type="submit" class="btn-predict">Calculate Passes</button>
          </div>
        </form>
      </div>

      <table class="passes-table">
        <thead>
          <tr>
            <th>Start Time (UTC)</th>
            <th>Max Elevation</th>
            <th>Trajectory</th>
            <th>Duration</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($sat_id)): ?>
            <tr><td colspan="4" style="color: var(--text-muted); text-align: center; padding: 24px;">Select a satellite and click "Calculate Passes".</td></tr>
          <?php elseif (empty($passes)): ?>
            <tr><td colspan="4" style="color: var(--text-muted); text-align: center; padding: 24px;">No visible passes found for the selected timeframe.</td></tr>
          <?php else: ?>
            <?php foreach ($passes as $p): ?>
              <tr>
                <td><strong style="color:var(--text-primary);"><?= date('Y-m-d H:i:s', $p['startUTC']); ?></strong></td>
                <td><span style="color:var(--accent-cyan); font-weight:600;"><?= $p['maxEl']; ?>°</span></td>
                <td><?= $p['startAzCompass']; ?> &rarr; <?= $p['maxAzCompass']; ?> &rarr; <?= $p['endAzCompass']; ?></td>
                <td><?= floor($p['duration'] / 60); ?>m <?= $p['duration'] % 60; ?>s</td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </main>
  </div>

</body>
</html>