<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Canvas Dashboard — Laravel Canvas</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('vendor/canvas/css/canvas.css') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <div id="dashboard-app">
        <header id="toolbar">
            <div class="logo">
                <span class="logo-icon">◆</span>
                <span class="logo-text">Canvas Dashboard</span>
            </div>
            <nav class="toolbar-nav">
                <a href="/canvas" class="toolbar-btn">
                    <span class="icon">◈</span> 3D View
                </a>
                <button id="btn-refresh" class="toolbar-btn">
                    <span class="icon">⟳</span> Refresh
                </button>
            </nav>
        </header>

        <main id="dashboard-content">
            <div class="dashboard-grid">
                <div class="dashboard-card" id="card-summary">
                    <h2>Architecture Summary</h2>
                    <div class="card-body">
                        <div class="metric-grid">
                            <div class="metric">
                                <span class="metric-value" id="total-nodes">-</span>
                                <span class="metric-label">Total Nodes</span>
                            </div>
                            <div class="metric">
                                <span class="metric-value" id="total-edges">-</span>
                                <span class="metric-label">Total Edges</span>
                            </div>
                            <div class="metric">
                                <span class="metric-value" id="avg-deps">-</span>
                                <span class="metric-label">Avg Deps</span>
                            </div>
                            <div class="metric">
                                <span class="metric-value" id="avg-health">-</span>
                                <span class="metric-label">Avg Health</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dashboard-card" id="card-health">
                    <h2>Health Distribution</h2>
                    <div class="card-body">
                        <canvas id="health-chart"></canvas>
                    </div>
                </div>

                <div class="dashboard-card" id="card-types">
                    <h2>Component Types</h2>
                    <div class="card-body">
                        <canvas id="types-chart"></canvas>
                    </div>
                </div>

                <div class="dashboard-card" id="card-god-classes">
                    <h2>God Classes</h2>
                    <div class="card-body">
                        <div id="god-classes-list">
                            <p class="text-muted">No god classes detected.</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        const API_BASE = '/api/canvas';

        async function loadDashboard() {
            try {
                const [dashboardRes, healthRes] = await Promise.all([
                    fetch(`${API_BASE}/dashboard`),
                    fetch(`${API_BASE}/health`)
                ]);

                const dashboard = await dashboardRes.json();
                const health = await healthRes.json();

                document.getElementById('total-nodes').textContent = dashboard.totalNodes;
                document.getElementById('total-edges').textContent = dashboard.totalEdges;
                document.getElementById('avg-deps').textContent = dashboard.averageDependencies;
                document.getElementById('avg-health').textContent = (dashboard.averageHealth * 100).toFixed(0) + '%';

                const healthCtx = document.getElementById('health-chart').getContext('2d');
                new Chart(healthCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Healthy', 'Moderate', 'Unhealthy'],
                        datasets: [{
                            data: [
                                dashboard.healthSummary.healthyCount,
                                dashboard.healthSummary.moderateCount,
                                dashboard.healthSummary.unhealthyCount
                            ],
                            backgroundColor: ['#00ff88', '#ffaa00', '#ff3355'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { position: 'bottom', labels: { color: '#aaa' } }
                        }
                    }
                });

                const typesCtx = document.getElementById('types-chart').getContext('2d');
                const typeLabels = Object.keys(dashboard.nodeTypeCounts);
                const typeValues = Object.values(dashboard.nodeTypeCounts);
                const typeColors = {
                    model: '#00ccff', controller: '#ff8800', job: '#aa66ff',
                    listener: '#66ddaa', policy: '#ff66aa', middleware: '#ffaa00',
                    provider: '#ff3355', route: '#8888ff'
                };

                new Chart(typesCtx, {
                    type: 'bar',
                    data: {
                        labels: typeLabels,
                        datasets: [{
                            data: typeValues,
                            backgroundColor: typeLabels.map(l => typeColors[l] || '#888'),
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        indexAxis: 'y',
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            x: { ticks: { color: '#aaa' }, grid: { color: '#222' } },
                            y: { ticks: { color: '#aaa' }, grid: { display: false } }
                        }
                    }
                });

                const godList = document.getElementById('god-classes-list');
                if (health.godClasses && health.godClasses.length > 0) {
                    godList.innerHTML = health.godClasses.map(g => `
                        <div class="god-class-item">
                            <strong>${g.node.label}</strong>
                            <div class="god-class-metrics">
                                <span>Score: ${g.godScore}</span>
                                <span>Complexity: ${g.complexity}</span>
                                <span>Methods: ${g.methodCount}</span>
                                <span>Deps: ${g.dependencyCount}</span>
                            </div>
                        </div>
                    `).join('');
                }
            } catch (e) {
                console.error('Dashboard load failed:', e);
            }
        }

        document.getElementById('btn-refresh')?.addEventListener('click', loadDashboard);
        loadDashboard();
    </script>
</body>
</html>
