document.addEventListener('DOMContentLoaded', () => {
  // 1. Initialize UI elements
  renderDummyUserSession();
  renderDummyKPIs();
  renderDummyActivityFeed();
  initCategoryChart();
});

// User Session Rendering Logic
function renderDummyUserSession() {
  const user = { name: "Kasun Perera", role: "admin" }; // Placeholder
  
  document.getElementById('user-display-name').textContent = user.name;
  document.getElementById('user-role-badge').textContent = user.role.toUpperCase();

  // Hide admin-only links if user is not an admin
  if (user.role !== 'admin') {
    document.getElementById('nav-users').style.display = 'none';
    document.getElementById('nav-audit').style.display = 'none';
  }
}

// Render Metrics
function renderDummyKPIs() {
  document.getElementById('stat-satellites').textContent = '128';
  document.getElementById('stat-users').textContent = '42';
  document.getElementById('stat-passes').textContent = '7';
  document.getElementById('stat-edits').textContent = '356';
}

// Render Recent Edits Activity Feed
function renderDummyActivityFeed() {
  const activities = [
    { sat: 'NOAA-19', action: 'UPDATE', user: 'user_nadeesha', time: '10m ago' },
    { sat: 'Starlink-2201', action: 'CREATE', user: 'user_thivanka', time: '1h ago' },
    { sat: 'ISS (ZARYA)', action: 'UPDATE', user: 'admin_kasun', time: '3h ago' },
    { sat: 'COSMOS 2251 DEB', action: 'DELETE', user: 'admin_kasun', time: '1d ago' }
  ];

  const feedContainer = document.getElementById('activity-feed');
  feedContainer.innerHTML = activities.map(item => `
    <li class="activity-item">
      <div class="activity-info">
        <strong>${item.sat}</strong>
        <span>${item.action} by ${item.user}</span>
      </div>
      <span class="activity-time">${item.time}</span>
    </li>
  `).join('');
}

// Chart.js Category Breakdown Chart
function initCategoryChart() {
  const ctx = document.getElementById('categoryChart').getContext('2d');
  
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: ['Space Station', 'Weather', 'Communication', 'Navigation', 'Debris'],
      datasets: [{
        label: 'Satellites',
        data: [12, 28, 54, 20, 14],
        backgroundColor: '#3b82f6',
        borderColor: '#22d3ee',
        borderWidth: 1,
        borderRadius: 4
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false }
      },
      scales: {
        x: {
          ticks: { color: '#7c8cae' },
          grid: { color: '#233355' }
        },
        y: {
          ticks: { color: '#7c8cae' },
          grid: { color: '#233355' }
        }
      }
    }
  });
}