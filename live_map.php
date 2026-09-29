<?php
// live_map.php - Real-Time Satellite Tracking Map & Live Camera
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>OrbitTrack - Live Map & Video</title>
  <link rel="stylesheet" href="assets/css/style.css">
  
  <!-- Leaflet CSS & JS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  
  <!-- satellite.js for orbital calculations -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/satellite.js/4.1.3/satellite.min.js"></script>

  <style>
    .map-grid {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 20px;
    }
    @media (max-width: 1100px) {
      .map-grid {
        grid-template-columns: 1fr;
      }
    }
    #map {
      width: 100%;
      height: 600px;
      border-radius: 10px;
      border: 1px solid var(--border-blue, #334155);
      background: #050b14;
    }
    .video-card {
      background: var(--card-navy, #1e293b);
      border: 1px solid var(--border-blue, #334155);
      border-radius: 10px;
      padding: 15px;
      display: flex;
      flex-direction: column;
    }
    .video-card h3 {
      margin-top: 0;
      margin-bottom: 12px;
      font-size: 1rem;
      color: #38bdf8;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .video-wrapper {
      position: relative;
      padding-bottom: 56.25%; /* 16:9 Aspect Ratio */
      height: 0;
      overflow: hidden;
      border-radius: 8px;
      border: 1px solid #334155;
    }
    .video-wrapper iframe {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
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
    .sat-marker {
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .sat-dot {
      width: 12px;
      height: 12px;
      background-color: #22d3ee;
      border: 2px solid #ffffff;
      border-radius: 50%;
      box-shadow: 0 0 10px #22d3ee, 0 0 20px #22d3ee;
      transition: transform 0.2s ease;
    }
    .sat-dot:hover {
      transform: scale(1.6);
      background-color: #38bdf8;
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
      <div class="top-bar" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <div>
          <h1>Live Satellite Tracking & Camera Feed</h1>
          <p>Real-time orbital propagation and live space video</p>
        </div>
        <div style="color: #94a3b8; font-size: 0.85rem;">
          Tracked Objects: <strong id="active-count" style="color:#38bdf8;">0</strong>
        </div>
      </div>

      <!-- Grid containing Map and Video Feed -->
      <div class="map-grid">
        
        <!-- Live Map Column -->
        <div id="map"></div>

        <!-- Live Stream Panel -->
        <div class="video-card">
          <h3>🔴 ISS Live Earth Camera (NASA Feed)</h3>
          <div class="video-wrapper">
            <iframe 
              src="https://www.youtube.com/embed/P9C25Un7x14?autoplay=1&mute=1" 
              title="NASA ISS Live Feed" 
              frameborder="0" 
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
              allowfullscreen>
            </iframe>
          </div>
          <p style="font-size: 0.8rem; color: #94a3b8; margin-top: 12px; line-height: 1.4;">
            Live High-Definition video feed of Earth from the International Space Station (ISS) external views. Note: Black screens indicate orbital darkness or signal transitions.
          </p>
        </div>

      </div>
    </main>

  </div>

  <script>
    // Initialize Map
    const map = L.map('map', {
      center: [20, 0],
      zoom: 2,
      minZoom: 2
    });

    // Esri World Imagery Satellite Map
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
                  <strong style="color:#38bdf8; font-size:1rem;">${sat.name}</strong><br>
                  <b>NORAD ID:</b> ${sat.norad_id}<br>
                  <b>Category:</b> ${sat.category}<br>
                  <b>Altitude:</b> ${alt} km<br>
                  <b>Lat/Lng:</b> ${lat.toFixed(2)}°, ${lng.toFixed(2)}°
                `);
              } else {
                const marker = L.marker([lat, lng], { icon: customIcon }).addTo(map);
                marker.bindPopup(`
                  <strong style="color:#38bdf8; font-size:1rem;">${sat.name}</strong><br>
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