<?php
// live_map.php - Real-Time Satellite Tracking Map
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/db.php';

// Fetch active satellites with TLE data
$stmt =$pdo->query("SELECT id, name, norad_id, category, tle_line1, tle_line2 FROM satellites WHERE status = 'Active' AND tle_line1 IS NOT NULL AND tle_line2 IS NOT NULL");
$satellites =$stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>OrbitTrack - Live Map</title>
  <link rel="stylesheet" href="asset/css/style.css">
  
  <!-- Leaflet CSS & JS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  
  <!-- satellite.js for orbital calculations -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/satellite.js/4.0.0/satellite.min.js"></script>

  <style>
    #map {
      width: 100%;
      height: calc(100vh - 120px);
      border-radius: 10px;
      border: 1px solid var(--border-blue, #334155);
      background: #0b1120;
    }
    .leaflet-popup-content-wrapper {
      background: #1e293b;
      color: #fff;
      border: 1px solid #38bdf8;
      border-radius: 8px;
    }
    .leaflet-popup-tip {
      background: #1e293b;
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
        <a href="live_map.php" class="active">Live Map</a>
        <a href="passes.php">Pass Predictions</a>
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
      <div class="top-bar" style="margin-bottom: 15px;">
        <div>
          <h1>Live Satellite Tracking Map</h1>
          <p>Real-time orbital propagation and global positioning</p>
        </div>
      </div>

      <!-- Map Container -->
      <div id="map"></div>
    </main>

  </div>

  <script>
    // Initialize Map centered globally
    const map = L.map('map').setView([20, 0], 2);

    // Free CARTO Dark Matter Tiles (No API key required)
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
      attribution: '&copy; OpenStreetMap &copy; CARTO',
      subdomains: 'abcd',
      maxZoom: 19
    }).addTo(map);

    // Load Satellites Data from PHP
    const satData = <?= json_encode($satellites); ?>;
    const markers = {};

    // Custom Glowing Satellite Icon
    const satIcon = L.divIcon({
      className: 'custom-sat-icon',
      html: '<div style="width: 12px; height: 12px; background: #38bdf8; border-radius: 50%; box-shadow: 0 0 10px #38bdf8;"></div>',
      iconSize: [12, 12]
    });

    // Update positions using satellite.js
    function updateSatellitePositions() {
      const now = new Date();

      satData.forEach(sat => {
        try {
          const satrec = satellite.twoline2satrec(sat.tle_line1, sat.tle_line2);
          const positionAndVelocity = satellite.propagate(satrec, now);
          const positionEci = positionAndVelocity.position;

          if (positionEci) {
            const gmst = satellite.gstime(now);
            const positionGd = satellite.eciToGeodetic(positionEci, gmst);

            const lat = satellite.degreesLat(positionGd.latitude);
            const lng = satellite.degreesLong(positionGd.longitude);
            const alt = Math.round(positionGd.height);

            if (markers[sat.id]) {
              markers[sat.id].setLatLng([lat, lng]);
            } else {
              const marker = L.marker([lat, lng], { icon: satIcon }).addTo(map);
              marker.bindPopup(`
                <strong style="color:#38bdf8; font-size:1.1rem;">${sat.name}</strong><br>
                <b>NORAD ID:</b> ${sat.norad_id}<br>
                <b>Category:</b> ${sat.category}<br>
                <b>Altitude:</b> ${alt} km
              `);
              markers[sat.id] = marker;
            }
          }
        } catch (e) {
          console.error("Error propagating satellite:", sat.name);
        }
      });
    }

    // Run propagation once and update every 3 seconds
    updateSatellitePositions();
    setInterval(updateSatellitePositions, 3000);
  </script>

</body>
</html>