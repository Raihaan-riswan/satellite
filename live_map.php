<?php
// live_map.php - Real-Time Satellite Tracking Map
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/db.php';

// Fetch active satellites with non-empty TLE data
$stmt =$pdo->query("
    SELECT id, name, norad_id, category, tle_line1, tle_line2 
    FROM satellites 
    WHERE status = 'Active' 
      AND tle_line1 IS NOT NULL AND tle_line1 != ''
      AND tle_line2 IS NOT NULL AND tle_line2 != ''
");
$satellites =$stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>OrbitTrack - Live Tracking Map</title>
  <link rel="stylesheet" href="assets/css/style.css">
  
  <!-- Leaflet CSS & JS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

  <!-- Satellite.js library for TLE propagation -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/satellite.js/4.0.0/satellite.min.js"></script>

  <style>
    #map {
      width: 100%;
      height: 650px;
      border-radius: 12px;
      border: 1px solid var(--border-blue);
    }
    .map-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }
    .status-indicator {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.85rem;
      color: var(--text-muted);
    }
    .pulse-dot {
      width: 10px;
      height: 10px;
      background-color: var(--status-active);
      border-radius: 50%;
      box-shadow: 0 0 8px var(--status-active);
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
      <div class="map-header">
        <div>
          <h1>Real-Time Live Map</h1>
          <p>Plotting orbital positions using SGP4 TLE propagation</p>
        </div>
        <div class="status-indicator">
          <div class="pulse-dot"></div>
          <span>Live Signal (Refreshing every 2s)</span>
        </div>
      </div>

      <!-- Map Container -->
      <div id="map"></div>
    </main>

  </div>

  <script>
    // 1. Pass PHP Satellites Data to JS
    const satellitesData = <?= json_encode($satellites); ?>;

    // 2. Initialize Dark Tilemap (CartoDB Dark Matter)
    const map = L.map('map').setView([0, 0], 2);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
      attribution: '&copy; OpenStreetMap &copy; CARTO',
      subdomains: 'abcd',
      maxZoom: 19
    }).addTo(map);

    // Custom Satellite Icon Marker
    const satIcon = L.divIcon({
      className: 'custom-sat-icon',
      html: `<div style="
        width: 12px;
        height: 12px;
        background-color: #22d3ee;
        border-radius: 50%;
        border: 2px solid #fff;
        box-shadow: 0 0 10px #22d3ee;
      "></div>`,
      iconSize: [12, 12],
      iconAnchor: [6, 6]
    });

    const markers = {};

    // 3. Function to Calculate Sat Position (Lat/Lng/Alt) using Satellite.js
    function getSatPosition(tle1, tle2) {
      try {
        const satrec = satellite.twoline2satrec(tle1, tle2);
        const now = new Date();
        const positionAndVelocity = satellite.propagate(satrec, now);
        const positionEci = positionAndVelocity.position;

        if (!positionEci) return null;

        const gmst = satellite.gstime(now);
        const positionGd = satellite.eciToGeodetic(positionEci, gmst);

        const latitude  = satellite.degreesLat(positionGd.latitude);
        const longitude = satellite.degreesLong(positionGd.longitude);
        const altitude  = Math.round(positionGd.height); // Altitude in km

        return { lat: latitude, lng: longitude, alt: altitude };
      } catch (err) {
        return null;
      }
    }

    // 4. Plot Initial Satellite Markers
    satellitesData.forEach(sat => {
      const pos = getSatPosition(sat.tle_line1, sat.tle_line2);
      if (pos) {
        const marker = L.marker([pos.lat, pos.lng], { icon: satIcon }).addTo(map);
        marker.bindPopup(`
          <div style="color: #0b1120;">
            <strong>${sat.name}</strong><br>
            NORAD ID: ${sat.norad_id}<br>
            Category: ${sat.category}<br>
            Altitude: ${pos.alt} km
          </div>
        `);
        markers[sat.id] = { marker, tle1: sat.tle_line1, tle2: sat.tle_line2, name: sat.name, norad: sat.norad_id, category: sat.category };
      }
    });

    // 5. Auto-Update Marker Positions Every 2 Seconds
    setInterval(() => {
      Object.keys(markers).forEach(id => {
        const item = markers[id];
        const pos = getSatPosition(item.tle1, item.tle2);
        if (pos) {
          item.marker.setLatLng([pos.lat, pos.lng]);
          item.marker.getPopup().setContent(`
            <div style="color: #0b1120;">
              <strong>${item.name}</strong><br>
              NORAD ID: ${item.norad}<br>
              Category: ${item.category}<br>
              Altitude: ${pos.alt} km
            </div>
          `);
        }
      });
    }, 2000);
  </script>

</body>
</html>