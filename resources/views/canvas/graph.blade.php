<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel Canvas</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="ws-host" content="{{ config('canvas.websocket.host') }}">
    <meta name="ws-port" content="{{ config('canvas.websocket.port') }}">
    <link rel="stylesheet" href="{{ asset('vendor/canvas/css/canvas.css') }}">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div id="canvas-container">
        <div id="loading-overlay">
            <div class="loader">
                <div class="loader-ring"></div>
                <div class="loader-text">Scanning codebase...</div>
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
                    <button class="toolbar-btn" data-filter="all" title="Show All">
                        <span class="icon">◉</span>
                    </button>
                    <button class="toolbar-btn" data-filter="model" title="Models">
                        <span class="icon" style="color: #00ccff">■</span>
                    </button>
                    <button class="toolbar-btn" data-filter="controller" title="Controllers">
                        <span class="icon" style="color: #ff8800">▲</span>
                    </button>
                    <button class="toolbar-btn" data-filter="job" title="Jobs">
                        <span class="icon" style="color: #aa66ff">●</span>
                    </button>
                    <button class="toolbar-btn" data-filter="listener" title="Listeners">
                        <span class="icon" style="color: #66ddaa">◆</span>
                    </button>
                    <button class="toolbar-btn" data-filter="policy" title="Policies">
                        <span class="icon" style="color: #ff66aa">★</span>
                    </button>
                    <button class="toolbar-btn" data-filter="middleware" title="Middleware">
                        <span class="icon" style="color: #ffaa00">⬡</span>
                    </button>
                    <button class="toolbar-btn" data-filter="provider" title="Providers">
                        <span class="icon" style="color: #ff3355">⬟</span>
                    </button>

                    <div class="toolbar-divider"></div>

                    <button id="btn-heatmap" class="toolbar-btn" title="Heat Map">
                        <span class="icon">🌡</span>
                    </button>
                    <button id="btn-snapshot" class="toolbar-btn" title="Take Snapshot">
                        <span class="icon">📸</span>
                    </button>
                    <button id="btn-export" class="toolbar-btn" title="Export">
                        <span class="icon">↓</span>
                    </button>
                    <button id="btn-dashboard" class="toolbar-btn" title="Dashboard">
                        <span class="icon">📊</span>
                    </button>
                </nav>

                <div class="connection-status" id="connection-status">
                    <span class="status-dot"></span>
                    <span class="status-text">Disconnected</span>
                </div>
            </header>

            <div id="stats-bar">
                <div class="stat"><span class="stat-value" id="stat-nodes">0</span> nodes</div>
                <div class="stat"><span class="stat-value" id="stat-edges">0</span> edges</div>
                <div class="stat"><span class="stat-value" id="stat-health">0%</span> health</div>
                <div class="stat"><span class="stat-value" id="stat-tests">0</span> tests</div>
            </div>
        </div>

        <aside id="side-panel" class="side-panel hidden">
            <div class="panel-header">
                <h3 id="panel-title">Component Details</h3>
                <button id="panel-close" class="panel-close">✕</button>
            </div>
            <div class="panel-body" id="panel-body">
                <div class="panel-loading">Loading...</div>
            </div>
        </aside>

        <div id="test-notification-container"></div>

        <div id="minimap" class="minimap">
            <canvas id="minimap-canvas"></canvas>
        </div>
    </div>

    <script>
        const CANVAS_CONFIG = {
            wsHost: document.querySelector('meta[name="ws-host"]')?.content || '127.0.0.1',
            wsPort: document.querySelector('meta[name="ws-port"]')?.content || '8081',
            apiBase: '/api/canvas',
            particleCount: {{ config('canvas.visualization.particle_count', 2000) }},
            nodeSpacing: {{ config('canvas.visualization.node_spacing', 8.0) }},
            animationSpeed: {{ config('canvas.visualization.animation_speed', 1.0) }},
            bgColor: '{{ config('canvas.visualization.background_color', '#0a0a1a') }}'
        };
    </script>
    <script src="{{ asset('vendor/canvas/js/canvas.js') }}"></script>
</body>
</html>
