<?php
// fetch_satellites.php - Sync live TLE data from CelesTrak into MySQL
require_once 'config/db.php';

// CelesTrak API endpoint for Brightest / Active satellites
$apiUrl = "https://celestrak.org/NORAD/elements/gp.php?GROUP=active&FORMAT=tle";

echo "Fetching satellite data from CelesTrak...<br>";

// Fetch raw TLE data from API
$tleData = @file_get_contents($apiUrl);

if ($tleData === FALSE) {
    die("Failed to fetch data from CelesTrak API.");
}

// Split data into lines
$lines = explode("\n", str_replace("\r", "", trim($tleData)));

$imported = 0;
$totalSatellites = count($lines) / 3; // Each TLE entry is 3 lines

for ($i = 0; $i < count($lines); $i += 3) {
    if (!isset($lines[$i + 2])) break;

    $name  = trim($lines[$i]);
    $line1 = trim($lines[$i + 1]);
    $line2 = trim($lines[$i + 2]);

    // Extract NORAD ID from line 1 (columns 3-7)
    $noradId = trim(substr($line1, 2, 5));

    // Determine basic category
    $category = 'General';
    if (strpos($name, 'ISS') !== false || strpos($name, 'TIANGONG') !== false) {
        $category = 'Space Station';
    } elseif (strpos($name, 'NOAA') !== false || strpos($name, 'GOES') !== false || strpos($name, 'METEOR') !== false) {
        $category = 'Weather';
    } elseif (strpos($name, 'STARLINK') !== false || strpos($name, 'ONEWEB') !== false) {
        $category = 'Communication';
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO satellites (name, norad_id, category, status, tle_line1, tle_line2)
            VALUES (:name, :norad, :category, 'Active', :line1, :line2)
            ON DUPLICATE KEY UPDATE 
                name = VALUES(name),
                tle_line1 = VALUES(tle_line1),
                tle_line2 = VALUES(tle_line2),
                status = 'Active'
        ");

        $stmt->execute([
            ':name'     => $name,
            ':norad'    => $noradId,
            ':category' => $category,
            ':line1'    => $line1,
            ':line2'    => $line2
        ]);

        $imported++;
    } catch (PDOException $e) {
        // Skip duplicate or erroneous records silently
        continue;
    }
}

echo "<h3 style='color:green;'>Successfully imported/updated $imported satellites!</h3>";
echo "<a href='index.php'>Go to Dashboard</a>";
?>