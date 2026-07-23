<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel Canvas — Analytics Dashboard</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="ws-host" content="{{ config('canvas.websocket.host') }}">
    <meta name="ws-port" content="{{ config('canvas.websocket.port') }}">
    <link rel="stylesheet" href="{{ url('/canvas/assets/css/canvas.css') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/vis-network@9.1.6/standalone/umd/vis-network.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <div id="dashboard-app">
        <header id="toolbar">
            <div class="logo">
                <span class="logo-icon">\u25C6</span>
                <span class="logo-text">Canvas Analytics</span>
            </div>
            <nav class="toolbar-nav" id="main-tabs">
                <button class="tab-btn active" data-tab="overview">Overview</button>
                <button class="tab-btn" data-tab="architecture">Architecture</button>
                <button class="tab-btn" data-tab="quality">Quality</button>
                <button class="tab-btn" data-tab="queries">Queries</button>
                <button class="tab-btn" data-tab="coverage">Coverage</button>
                <button class="tab-btn" data-tab="suggestions">Suggestions</button>
                <button class="tab-btn" data-tab="sprint">Sprint Review</button>
            </nav>
            <button class="theme-toggle" id="theme-toggle" title="Toggle theme">Light</button>
            <div class="connection-status" id="connection-status">
                <span class="status-dot"></span>
                <span class="status-text">Disconnected</span>
            </div>
        </header>

        <main id="dashboard-content">

            <!-- OVERVIEW TAB -->
            <section class="tab-panel active" id="tab-overview">
                <div class="summary-cards" id="summary-cards"></div>
                <div class="dashboard-grid">
                    <div class="dashboard-card"><h2>Health Distribution</h2><div class="card-body"><canvas id="health-chart"></canvas></div></div>
                    <div class="dashboard-card"><h2>Component Types</h2><div class="card-body"><canvas id="types-chart"></canvas></div></div>
                    <div class="dashboard-card"><h2>Complexity by Type</h2><div class="card-body"><canvas id="complexity-chart"></canvas></div></div>
                    <div class="dashboard-card"><h2>Health by Type</h2><div class="card-body"><canvas id="health-type-chart"></canvas></div></div>
                    <div class="dashboard-card"><h2>Dependency Distribution</h2><div class="card-body"><canvas id="dep-chart"></canvas></div></div>
                    <div class="dashboard-card"><h2>Test Coverage</h2><div class="card-body"><canvas id="test-chart"></canvas></div></div>
                </div>
            </section>

            <!-- ARCHITECTURE TAB -->
            <section class="tab-panel" id="tab-architecture">
                <div class="arch-header">
                    <div class="arch-stats" id="arch-stats"></div>
                    <div class="arch-legend" id="arch-legend"></div>
                </div>
                <div class="arch-container"><div id="architecture-graph" style="height:550px;"></div></div>
                <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;" id="graph-legend"></div>
            </section>

            <!-- QUALITY TAB -->
            <section class="tab-panel" id="tab-quality">
                <div class="dashboard-grid">
                    <div class="dashboard-card full-width"><h2>Code Quality Overview</h2><div class="card-body" id="quality-metrics"></div></div>
                    <div class="dashboard-card full-width"><h2>God Classes</h2><div class="card-body" id="god-classes-list"></div></div>
                    <div class="dashboard-card full-width"><h2>Dependency Analysis</h2><div class="card-body" id="dependency-list"></div></div>
                </div>
            </section>

            <!-- QUERIES TAB -->
            <section class="tab-panel" id="tab-queries">
                <div class="dashboard-grid">
                    <div class="dashboard-card full-width"><h2>Query & Relationship Analysis</h2><div class="card-body" id="query-analysis-list"></div></div>
                    <div class="dashboard-card"><h2>Component Distribution</h2><div class="card-body"><canvas id="query-chart"></canvas></div></div>
                    <div class="dashboard-card"><h2>Performance Insights</h2><div class="card-body" id="query-analysis"></div></div>
                </div>
            </section>

            <!-- COVERAGE TAB -->
            <section class="tab-panel" id="tab-coverage">
                <div class="dashboard-grid">
                    <div class="dashboard-card full-width"><h2>Test Coverage Overview</h2><div class="card-body" id="coverage-overview"></div></div>
                    <div class="dashboard-card full-width"><h2>Per-Component Coverage</h2><div class="card-body" id="coverage-breakdown"></div></div>
                </div>
            </section>

            <!-- SUGGESTIONS TAB -->
            <section class="tab-panel" id="tab-suggestions">
                <div class="suggestions-header">
                    <h2>Actionable Insights</h2>
                    <p style="color:var(--text-muted);">AI-powered suggestions to improve your codebase</p>
                </div>
                <div id="suggestions-list"></div>
            </section>

            <!-- SPRINT REVIEW TAB -->
            <section class="tab-panel" id="tab-sprint">
                <div class="dashboard-grid">
                    <div class="dashboard-card full-width"><h2>Executive Summary</h2><div class="card-body" id="sprint-summary"></div></div>
                    <div class="dashboard-card full-width"><h2>Technical Debt & Health</h2><div class="card-body" id="debt-trajectory"></div></div>
                    <div class="dashboard-card full-width"><h2>Recommended Actions</h2><div class="card-body" id="sprint-actions"></div></div>
                </div>
            </section>

        </main>
    </div>

    <script>
        const CANVAS_CONFIG = {
            wsHost: document.querySelector('meta[name="ws-host"]')?.content || '127.0.0.1',
            wsPort: document.querySelector('meta[name="ws-port"]')?.content || '8081',
            apiBase: '/api/canvas',
        };
    </script>
    <script src="{{ url('/canvas/assets/js/dashboard.js') }}"></script>
</body>
</html>
