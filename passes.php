<?php
// passes.php - Satellite Pass Predictions
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/db.php';

// Default observer location (e.g., Colombo / Sri Lanka coordinates)
$observer_lat = $_GET['lat'] ?? '6.9271';
$observer_lng = $_GET['lng'] ?? '79.8612';
$sat_id       = $_GET['sat_id'] ?? '';

// Fetch active satellites for dropdown selection
$sat_stmt = $pdo->query("SELECT id, name, norad_id FROM satellites WHERE status = 'Active' ORDER BY name ASC");
$satellites = $sat_stmt->fetchAll();

// Optional N2YO API Key (Replace with your actual key if available)
$api_key = 'YOUR_N2YO_API_KEY'; 
$passes = [];
$error_msg = '';

if (!empty($sat_id)) {
    // Find selected satellite's NORAD ID
    $selected_sat = null;
    foreach ($satellites as $s) {
        if ($s['id'] == $sat_id) {
            $selected_sat = $s;
            break;
        }
    }

    if ($selected_sat && $api_key !== 'YOUR_N2YO_API_KEY') {
        // Fetch real visual pass predictions from N2YO API
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
        // Mock pass data for local testing when API key is not set
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
    .filter-card {
      background: var(--card-navy);
      border: 1px solid var(--border-blue);
      border-radius: 8px;
      padding: 20px;
      margin-bottom: 20px;
    }
    .form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 15px;
      align-items: end;
    }
    .form-group label {
      display: block;
      font-size: 0.85rem;
      color: var(--text-muted);
      margin-bottom: 6px;
    }
    .form-group input, .form-group select {
      width: 100%;
      padding: 9px 12px;
      background: var(--bg-navy);
      border: 1px solid var(--border-blue);
      color: var(--text-primary);
      border-radius: 6px;
    }
    .btn-predict {
      padding: 10px 18px;
      background: var(--accent-primary);
      color: #fff;
      border: none;
      border-radius: 6px;
      font-weight: 600;
      cursor: pointer;
    }
    .passes-table {
      width: 100%;
      border-collapse: collapse;
      background: var(--card-navy);
      border-radius: 8px;
      overflow: hidden;
      border: 1px solid var(--border-blue);
    }
    .passes-table th, .passes-table td {
      padding: 12px 16px;
      text-align: left;
      border-bottom: 1px solid var(--border-blue);
    }
    .passes-table th {
      background: rgba(35, 51, 85, 0.5);
      color: var(--text-muted);
      font-size: 0.85rem;
      text-transform: uppercase;
    }
  </style>
</head>
<body>

  <div class="dashboard-container">
    
    <!-- Sidebar Navigation -->
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

    <!-- Main Workspace -->
    <main class="main-content">
      <div class="top-bar">
        <div>
          <h1>Pass Predictions</h1>
          <p>Calculate visible satellite passes over your observation coordinates</p>
        </div>
      </div>

      <!-- Location & Satellite Selector Form -->
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

      <!-- Pass Output Table -->
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
            <tr><td colspan="4" style="color: var(--text-muted); text-align: center;">Select a satellite and click "Calculate Passes".</td></tr>
          <?php elseif (empty($passes)): ?>
            <tr><td colspan="4" style="color: var(--text-muted); text-align: center;">No visible passes found for the selected timeframe.</td></tr>
          <?php else: ?>
            <?php foreach ($passes as $p): ?>
              <tr>
                <td><strong><?= date('Y-m-d H:i:s', $p['startUTC']); ?></strong></td>
                <td><?= $p['maxEl']; ?>°</td>
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