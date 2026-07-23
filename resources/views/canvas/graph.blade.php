<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel Canvas — Architecture Graph</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="ws-host" content="{{ config('canvas.websocket.host') }}">
    <meta name="ws-port" content="{{ config('canvas.websocket.port') }}">
    @php $publishedCss = public_path('vendor/canvas/css/canvas.css'); $cssUrl = file_exists($publishedCss) ? asset('vendor/canvas/css/canvas.css') : url('/canvas/assets/css/canvas.css'); @endphp
    <link rel="stylesheet" href="{{ $cssUrl }}">
    <script src="https://unpkg.com/vis-network@9.1.6/standalone/umd/vis-network.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div id="canvas-container">
        <div id="loading-overlay">
            <div class="loader">
                <div class="loader-ring"></div>
                <div class="loader-text">Loading architecture graph...</div>
            </div>
        </div>

        <div id="ui-overlay">
            <header id="toolbar">
                <div class="logo">
                    <span class="logo-icon">◆</span>
                    <span class="logo-text">Canvas</span>
                </div>

                <div class="search-box">
                    <input type="text" id="search-input" placeholder="Search components..." autocomplete="off">
                    <div id="search-results" class="search-results hidden"></div>
                </div>

                <nav class="toolbar-nav">
                    <button class="toolbar-btn" data-filter="all" title="Show All">All</button>
                    <button class="toolbar-btn" data-filter="model" title="Models">Models</button>
                    <button class="toolbar-btn" data-filter="controller" title="Controllers">Controllers</button>
                    <button class="toolbar-btn" data-filter="route" title="Routes">Routes</button>

                    <div class="toolbar-divider"></div>

                    <button id="btn-heatmap" class="toolbar-btn" title="Heat Map">Heat</button>
                    <button id="btn-snapshot" class="toolbar-btn" title="Take Snapshot">Snap</button>
                    <button id="btn-export" class="toolbar-btn" title="Export">Export</button>
                    <button id="btn-dashboard" class="toolbar-btn" title="Dashboard">Dashboard</button>
                </nav>

                <button class="theme-toggle" id="graph-theme-toggle" title="Toggle theme">🌙 Light</button>
                <div class="connection-status" id="connection-status">
                    <span class="status-dot"></span>
                    <span class="status-text">Disconnected</span>
                </div>
            </header>

            <div id="stats-bar">
                <div class="stat"><span class="stat-value" id="stat-nodes">0</span> nodes</div>
                <div class="stat"><span class="stat-value" id="stat-edges">0</span> edges</div>
                <div class="stat"><span class="stat-value" id="stat-health">0%</span> avg health</div>
                <div class="stat"><span class="stat-value" id="stat-tests">0</span> test edges</div>
            </div>
        </div>

        <aside id="side-panel" class="side-panel">
            <div class="panel-header">
                <h3 id="panel-title">Component Details</h3>
                <button id="panel-close" class="panel-close">✕</button>
            </div>
            <div class="panel-body" id="panel-body">
                <div class="panel-loading">Select a component to inspect</div>
            </div>
        </aside>

        <div id="test-notification-container"></div>
    </div>

    <script>
        const CANVAS_CONFIG = {
            wsHost: document.querySelector('meta[name="ws-host"]')?.content || '127.0.0.1',
            wsPort: document.querySelector('meta[name="ws-port"]')?.content || '8081',
            apiBase: '/api/canvas',
        };
    </script>
    @php $publishedJs = public_path('vendor/canvas/js/canvas.js'); $jsUrl = file_exists($publishedJs) ? asset('vendor/canvas/js/canvas.js') : url('/canvas/assets/js/canvas.js'); @endphp
    <script src="{{ $jsUrl }}"></script>
</body>
</html>
