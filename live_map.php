<?php
// live_map.php - Real-Time Satellite Tracking Map
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/db.php';

$stmt =$pdo->query("SELECT id, name, norad_id, category, tle_line1, tle_line2 FROM satellites WHERE status = 'Active' AND tle_line1 IS NOT NULL AND tle_line2 IS NOT NULL");
$satellites =$stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>OrbitTrack - Live Map</title>
  <link rel="stylesheet" href="asset/css/style.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/satellite.js/4.1.3/satellite.min.js"></script>
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
    .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .top-bar h1 { font-size: 1.6rem; margin: 0 0 6px 0; font-weight: 600; }
    .top-bar p { color: var(--text-muted); margin: 0; font-size: 0.88rem; }
    #map {
      width: 100%;
      height: calc(100vh - 160px);
      min-height: 500px;
      border-radius: 12px;
      border: 1px solid var(--border-blue);
      background: #050b14;
    }
    .leaflet-popup-content-wrapper {
      background: var(--card-navy);
      color: var(--text-primary);
      border: 1px solid var(--accent-cyan);
      border-radius: 8px;
    }
    .leaflet-popup-tip { background: var(--card-navy); }
    .sat-marker { display: flex; align-items: center; justify-content: center; }
    .sat-dot {
      width: 12px;
      height: 12px;
      background-color: var(--accent-cyan);
      border: 2px solid #ffffff;
      border-radius: 50%;
      box-shadow: 0 0 10px var(--accent-cyan);
      transition: transform 0.2s ease;
    }
    .sat-dot:hover { transform: scale(1.5); }
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

    <main class="main-content">
      <div class="top-bar">
        <div>
          <h1>Live Satellite Tracking Map</h1>
          <p>Real-time orbital propagation and global positioning</p>
        </div>
        <div style="color: var(--text-muted); font-size: 0.88rem; background: var(--card-navy); border: 1px solid var(--border-blue); padding: 8px 14px; border-radius: 20px;">
          Tracked Objects: <strong id="active-count" style="color:var(--accent-cyan);">0</strong>
        </div>
      </div>

      <div id="map"></div>
    </main>
  </div>

  <script>
    const map = L.map('map', { center: [20, 0], zoom: 2, minZoom: 2 });

    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
      attribution: 'Tiles &copy; Esri',
      maxZoom: 18
    }).addTo(map);

    const rawSatellites = <?= json_encode($satellites); ?>;
    const markers = {};

    const customIcon = L.divIcon({
      className: 'sat-marker',
      html: '<div class="sat-dot"></div>',
      iconSize: [16, 16],
      iconAnchor: [8, 8]
    });

    function normalizeLongitude(lng) {
      while (lng > 180) lng -= 360;
      while (lng < -180) lng += 360;
      return lng;
    }

    function updatePositions() {
      const now = new Date();
      let activeCount = 0;

      rawSatellites.forEach(sat => {
        if (!sat.tle_line1 || !sat.tle_line2) return;

        try {
          const satrec = satellite.twoline2satrec(sat.tle_line1.trim(), sat.tle_line2.trim());
          const positionAndVelocity = satellite.propagate(satrec, now);
          const positionEci = positionAndVelocity.position;

          if (positionEci && !isNaN(positionEci.x)) {
            const gmst = satellite.gstime(now);
            const positionGd = satellite.eciToGeodetic(positionEci, gmst);

            let lat = satellite.degreesLat(positionGd.latitude);
            let lng = normalizeLongitude(satellite.degreesLong(positionGd.longitude));
            let alt = Math.round(positionGd.height);

            if (!isNaN(lat) && !isNaN(lng)) {
              activeCount++;

              if (markers[sat.id]) {
                markers[sat.id].setLatLng([lat, lng]);
                markers[sat.id].setPopupContent(`
                  <strong style="color:var(--accent-cyan); font-size:1rem;">${sat.name}</strong><br>
                  <b>NORAD ID:</b> ${sat.norad_id}<br>
                  <b>Category:</b> ${sat.category}<br>
                  <b>Altitude:</b> ${alt} km<br>
                  <b>Lat/Lng:</b> ${lat.toFixed(2)}°, ${lng.toFixed(2)}°
                `);
              } else {
                const marker = L.marker([lat, lng], { icon: customIcon }).addTo(map);
                marker.bindPopup(`
                  <strong style="color:var(--accent-cyan); font-size:1rem;">${sat.name}</strong><br>
                  <b>NORAD ID:</b> ${sat.norad_id}<br>
                  <b>Category:</b> ${sat.category}<br>
                  <b>Altitude:</b> ${alt} km<br>
                  <b>Lat/Lng:</b> ${lat.toFixed(2)}°, ${lng.toFixed(2)}°
                `);
                markers[sat.id] = marker;
              }
            }
          }
        } catch (e) {
          console.warn("Propagation error:", sat.name);
        }
      });

      document.getElementById('active-count').innerText = activeCount;
    }

    setTimeout(() => {
      map.invalidateSize();
      updatePositions();
    }, 200);

    setInterval(updatePositions, 2000);
  </script>
</body>
</html>