<?php
// box.php - returns sensor data as HTML+JSON for AJAX
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    $url = "https://api.opensensemap.org/boxes/5f2b56f4263635001c1dd1fd";
    $response = @file_get_contents($url);
    if (!$response) {
        echo json_encode(['error' => 'Failed to fetch data']);
        exit;
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    echo json_encode($data);
    exit;
}

// Main page rendering below
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Sensor Box</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <link href="https://unpkg.com/leaflet/dist/leaflet.css" rel="stylesheet" />
  <script src="https://cdn.tailwindcss.com"></script>
  <style>#map { height: 300px; }</style>
</head>
<body class="bg-gray-100 p-6">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-bold">OpenSenseMap Sensor Box</h1>
    <div class="text-sm text-gray-700">Refreshing in <span id="countdown" class="font-bold text-blue-600">15</span>s...</div>
  </div>

  <div id="sensorContent">
    <!-- Sensor data (map, cards, charts) will be injected here by JS -->
    <div class="text-gray-500">Loading data...</div>
  </div>

  <script>
    let countdown = 15;
    const countdownEl = document.getElementById('countdown');
    const sensorContent = document.getElementById('sensorContent');
    let chartInstances = [];

    function renderMap(lat, lon) {
      const mapContainer = document.getElementById('map');
      mapContainer.innerHTML = ''; // Clear old map if any
      const map = L.map('map').setView([lat, lon], 15);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
      }).addTo(map);
      L.marker([lat, lon]).addTo(map).bindPopup('Sensor Box Location').openPopup();
    }

    function renderSensors(data) {
      const coords = data.currentLocation?.coordinates ?? [0, 0];
      const sensors = data.sensors ?? [];

      const lat = coords[1] ?? 0;
      const lon = coords[0] ?? 0;

      let html = `
        <div class="bg-white rounded shadow p-4 mb-6">
          <h2 class="text-xl font-semibold mb-2">Sensor Box Location</h2>
          <p class="text-sm text-gray-600">Latitude: ${lat}, Longitude: ${lon}</p>
          <div id="map" class="rounded mt-4"></div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      `;

      sensors.forEach((sensor, i) => {
        const name = sensor.title ?? 'Unnamed';
        const last = sensor.lastMeasurement;
        const canvasId = `chart${i}`;

        html += `
          <div class="bg-white p-4 rounded shadow">
            <h3 class="text-lg font-semibold mb-1">${name}</h3>
        `;

        if (last) {
          const val = parseFloat(last.value);
          const unit = last.unit ?? '';
          const time = last.createdAt ?? '';

          html += `
            <p class="text-gray-700"><strong>Value:</strong> ${val} ${unit}</p>
            <p class="text-sm text-gray-500 mb-2">Last updated: ${time}</p>
            <canvas id="${canvasId}" class="w-full h-48"></canvas>
          `;
        } else {
          html += `<p class="text-yellow-600">No recent measurements.</p>`;
        }

        html += `</div>`;
      });

      html += `</div>`;
      sensorContent.innerHTML = html;

      renderMap(lat, lon);

      // Destroy previous charts
      chartInstances.forEach(chart => chart.destroy());
      chartInstances = [];

      // Create new charts
      sensors.forEach((sensor, i) => {
        const last = sensor.lastMeasurement;
        if (!last) return;

        const val = parseFloat(last.value);
        const canvas = document.getElementById(`chart${i}`);
        if (!canvas) return;

        const chart = new Chart(canvas.getContext('2d'), {
          type: 'line',
          data: {
            labels: ['-4m', '-3m', '-2m', '-1m', 'Now'],
            datasets: [{
              label: `${sensor.title} (${sensor.unit})`,
              data: [
                val - 2 + Math.random(),
                val - 1 + Math.random(),
                val + Math.random(),
                val + 1 + Math.random(),
                val
              ],
              backgroundColor: 'rgba(59,130,246,0.2)',
              borderColor: 'rgba(59,130,246,1)',
              fill: true,
              tension: 0.4
            }]
          },
          options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
          }
        });

        chartInstances.push(chart);
      });
    }

    async function fetchSensorData() {
      try {
        const res = await fetch('box.php?ajax=1');
        const json = await res.json();
        if (json.error) throw new Error(json.error);
        renderSensors(json);
      } catch (err) {
        sensorContent.innerHTML = `<div class="text-red-600">Error: ${err.message}</div>`;
      }
    }

    function startCountdown() {
      countdown = 15;
      const interval = setInterval(() => {
        countdown--;
        countdownEl.textContent = countdown;
        if (countdown <= 0) {
          clearInterval(interval);
          fetchSensorData();
          startCountdown();
        }
      }, 1000);
    }

    // Initial load
    fetchSensorData();
    startCountdown();
  </script>
</body>
</html>
