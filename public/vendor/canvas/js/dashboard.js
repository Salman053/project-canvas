(function () {
    'use strict';

    const API = (window.CANVAS_CONFIG && window.CANVAS_CONFIG.apiBase) || '/api/canvas';
    const WS_HOST = (window.CANVAS_CONFIG && window.CANVAS_CONFIG.wsHost) || '127.0.0.1';
    const WS_PORT = (window.CANVAS_CONFIG && window.CANVAS_CONFIG.wsPort) || '8081';

    let analyticsData = null;
    let charts = {};
    let ws = null;
    let network = null;

    var TYPE_COLORS = {
        model: '#00ccff', controller: '#ff8800', job: '#aa66ff',
        listener: '#66ddaa', policy: '#ff66aa', middleware: '#ffaa00',
        provider: '#ff3355', route: '#8888ff'
    };

    var TYPE_LABELS = {
        model: 'Model', controller: 'Controller', job: 'Job',
        listener: 'Listener', policy: 'Policy', middleware: 'Middleware',
        provider: 'Provider', route: 'Route'
    };

    function init() {
        loadAnalytics();
        setupTabs();
        setupThemeToggle();
        connectWebSocket();
    }

    function setupThemeToggle() {
        var btn = document.getElementById('theme-toggle');
        if (!btn) return;
        var saved = localStorage.getItem('canvas-theme') || 'dark';
        document.documentElement.setAttribute('data-theme', saved);
        btn.innerHTML = saved === 'dark' ? '\u2600\uFE0F Light' : '\uD83C\uDF19 Dark';

        btn.addEventListener('click', function () {
            var current = document.documentElement.getAttribute('data-theme') || 'dark';
            var next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('canvas-theme', next);
            btn.innerHTML = next === 'dark' ? '\u2600\uFE0F Light' : '\uD83C\uDF19 Dark';
            if (charts.health) { charts.health.resize(); charts.health.update(); }
            if (charts.types) { charts.types.resize(); charts.types.update(); }
            if (charts.complexity) { charts.complexity.resize(); charts.complexity.update(); }
            if (charts.healthType) { charts.healthType.resize(); charts.healthType.update(); }
        });
    }

    function loadAnalytics() {
        showLoading(true);
        fetch(API + '/analytics')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                analyticsData = data;
                renderAll();
                showLoading(false);
            })
            .catch(function (err) {
                console.error('Failed to load analytics:', err);
                showLoading(false);
            });
    }

    function renderAll() {
        if (!analyticsData) return;
        renderSummaryCards();
        renderHealthChart();
        renderTypesChart();
        renderComplexityChart();
        renderHealthTypeChart();
        renderQualityMetrics();
        renderGodClasses();
        renderDependencies();
        renderCoverage();
        renderSuggestions();
        renderSprintReview();
    }

    function showLoading(show) {
        var el = document.getElementById('loading-overlay');
        if (!el) {
            el = document.createElement('div');
            el.id = 'loading-overlay';
            el.innerHTML = '<div class="loader"><div class="loader-ring"></div><div class="loader-text">Loading analytics...</div></div>';
            el.style.cssText = 'position:fixed;inset:0;background:#0a0a1a;display:flex;align-items:center;justify-content:center;z-index:9999;';
            document.body.appendChild(el);
        }
        el.style.display = show ? 'flex' : 'none';
    }

    function setupTabs() {
        document.querySelectorAll('.tab-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var tab = this.dataset.tab;
                document.querySelectorAll('.tab-btn').forEach(function (b) { b.classList.remove('active'); });
                this.classList.add('active');
                document.querySelectorAll('.tab-panel').forEach(function (p) { p.classList.remove('active'); });
                var panel = document.getElementById('tab-' + tab);
                if (panel) panel.classList.add('active');
                if (tab === 'architecture') setTimeout(renderArchitectureGraph, 100);
            });
        });
    }

    function renderSummaryCards() {
        var s = analyticsData.summary;
        var cards = [
            { label: 'Total Components', value: s.totalNodes, color: '#00ccff', icon: 'd' },
            { label: 'Relationships', value: s.totalEdges, color: '#aa66ff', icon: '#' },
            { label: 'Avg Health', value: (s.averageHealth * 100).toFixed(0) + '%', color: s.averageHealth >= 0.8 ? '#00ff88' : s.averageHealth >= 0.5 ? '#ffaa00' : '#ff3355', icon: 'H' },
            { label: 'Healthy/Mod/Unhealthy', value: s.healthyCount + ' / ' + s.moderateCount + ' / ' + s.unhealthyCount, color: '#888', icon: 'B' },
            { label: 'Routes', value: s.routeCount, color: '#8888ff', icon: 'R' },
            { label: 'Avg Dependencies', value: s.averageDependencies.toFixed(2), color: '#ff8800', icon: 'D' },
            { label: 'God Classes', value: s.godClassCount, color: s.godClassCount > 0 ? '#ff3355' : '#00ff88', icon: 'G' },
            { label: 'Suggestions', value: s.suggestionCount, color: s.suggestionCount > 0 ? '#ffaa00' : '#00ff88', icon: 'S' },
        ];
        var c = document.getElementById('summary-cards');
        if (!c) return;
        c.innerHTML = cards.map(function (c) {
            return '<div class="summary-card"><div class="sc-icon" style="color:' + c.color + '">' + c.icon + '</div><div class="sc-body"><div class="sc-value" style="color:' + c.color + '">' + c.value + '</div><div class="sc-label">' + c.label + '</div></div></div>';
        }).join('');
    }

    function themeText() {
        return document.documentElement.getAttribute('data-theme') === 'light' ? '#666' : '#888';
    }
    function themeGrid() {
        return document.documentElement.getAttribute('data-theme') === 'light' ? 'rgba(0,0,0,0.06)' : 'rgba(255,255,255,0.04)';
    }
    function themeGridStrong() {
        return document.documentElement.getAttribute('data-theme') === 'light' ? 'rgba(0,0,0,0.1)' : 'rgba(255,255,255,0.08)';
    }
    function themeBg() {
        return document.documentElement.getAttribute('data-theme') === 'light' ? '#f0f2f5' : '#0a0a1a';
    }
    function themeSecondary() {
        return document.documentElement.getAttribute('data-theme') === 'light' ? '#555' : '#aaa';
    }

    function renderHealthChart() {
        var s = analyticsData.summary;
        var ctx = document.getElementById('health-chart');
        if (!ctx) return;
        if (charts.health) charts.health.destroy();
        charts.health = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Healthy', 'Moderate', 'Unhealthy'],
                datasets: [{ data: [s.healthyCount, s.moderateCount, s.unhealthyCount], backgroundColor: ['#00ff88', '#ffaa00', '#ff3355'], borderWidth: 0 }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { color: themeText(), padding: 12, font: { size: 11 } } } },
                cutout: '70%',
            }
        });
    }

    function renderTypesChart() {
        var types = analyticsData.performance.nodeTypeCounts || {};
        var ctx = document.getElementById('types-chart');
        if (!ctx) return;
        if (charts.types) charts.types.destroy();
        var labels = Object.keys(types).map(function (t) { return TYPE_LABELS[t] || t; });
        var values = Object.values(types);
        var colors = Object.keys(types).map(function (t) { return TYPE_COLORS[t] || '#888'; });
        charts.types = new Chart(ctx, {
            type: 'bar',
            data: { labels: labels, datasets: [{ data: values, backgroundColor: colors, borderRadius: 4, borderSkipped: false }] },
            options: {
                responsive: true, maintainAspectRatio: false, indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { color: themeText(), font: { size: 11 } }, grid: { color: themeGrid() } },
                    y: { ticks: { color: themeSecondary(), font: { size: 11 } }, grid: { display: false } }
                }
            }
        });
    }

    function renderComplexityChart() {
        var q = analyticsData.quality;
        var ctx = document.getElementById('complexity-chart');
        if (!ctx) return;
        if (charts.complexity) charts.complexity.destroy();
        var labels = Object.keys(q.averageComplexityByType).map(function (t) { return TYPE_LABELS[t] || t; });
        var values = Object.values(q.averageComplexityByType);
        var colors = Object.keys(q.averageComplexityByType).map(function (t) { return TYPE_COLORS[t] || '#888'; });
        charts.complexity = new Chart(ctx, {
            type: 'radar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Avg Complexity', data: values,
                    backgroundColor: 'rgba(0,204,255,0.1)', borderColor: '#00ccff', borderWidth: 2,
                    pointBackgroundColor: colors, pointBorderColor: themeBg(), pointRadius: 4,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { labels: { color: themeText(), font: { size: 10 } } } },
                scales: {
                    r: {
                        angleLines: { color: themeGrid() },
                        grid: { color: themeGrid() },
                        pointLabels: { color: themeSecondary(), font: { size: 10 } },
                        ticks: { color: themeText(), backdropColor: 'transparent', font: { size: 9 } }
                    }
                }
            }
        });
    }

    function renderHealthTypeChart() {
        var q = analyticsData.quality;
        var ctx = document.getElementById('health-type-chart');
        if (!ctx) return;
        if (charts.healthType) charts.healthType.destroy();
        var labels = Object.keys(q.averageHealthByType).map(function (t) { return TYPE_LABELS[t] || t; });
        var values = Object.keys(q.averageHealthByType).map(function (t) { return q.averageHealthByType[t] * 100; });
        var colors = values.map(function (v) { return v >= 80 ? '#00ff88' : v >= 50 ? '#ffaa00' : '#ff3355'; });
        charts.healthType = new Chart(ctx, {
            type: 'polarArea',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors.map(function (c) { return c + '66'; }),
                    borderColor: colors, borderWidth: 2,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { color: themeText(), padding: 8, font: { size: 10 } } } },
                scales: { r: { grid: { color: themeGrid() }, ticks: { display: false } } }
            }
        });
    }

    function renderQualityMetrics() {
        var q = analyticsData.quality;
        var s = analyticsData.summary;
        var c = document.getElementById('quality-metrics');
        if (!c) return;
        var metrics = [
            { label: 'Average Complexity', value: q.averageComplexity, max: 20, good: '< 5' },
            { label: 'High Complexity', value: q.highComplexityCount, max: s.totalNodes || 1, good: '0' },
            { label: 'High Dependency', value: q.highDependencyCount, max: s.totalNodes || 1, good: '0' },
            { label: 'God Classes', value: s.godClassCount, max: s.totalNodes || 1, good: '0' },
        ];
        c.innerHTML = '<div class="metrics-grid">' + metrics.map(function (m) {
            var pct = Math.min(100, (m.value / m.max) * 100);
            var color = pct < 25 ? '#00ff88' : pct < 50 ? '#ffaa00' : '#ff3355';
            return '<div class="metric-card"><div class="metric-header"><span class="metric-label">' + m.label + '</span><span class="metric-value" style="color:' + color + '">' + m.value + '</span></div><div class="metric-bar"><div class="metric-fill" style="width:' + pct + '%;background:' + color + '"></div></div><span class="metric-target">Target: ' + m.good + '</span></div>';
        }).join('') + '</div>';
    }

    function renderGodClasses() {
        var gods = analyticsData.quality.godClasses || [];
        var c = document.getElementById('god-classes-list');
        if (!c) return;
        if (gods.length === 0) {
            c.innerHTML = '<p class="text-muted">No god classes detected. Your codebase has a healthy architecture.</p>';
            return;
        }
        c.innerHTML = gods.map(function (g) {
            var score = g.godScore || 0;
            var color = score > 25 ? '#ff3355' : score > 20 ? '#ffaa00' : '#ff8800';
            return '<div class="god-class-item"><div class="god-header"><span style="color:' + color + ';font-weight:700;">' + g.node.label + '</span><span class="god-score" style="background:' + color + '">Score: ' + score.toFixed(1) + '</span></div><div class="god-metrics"><span>Complexity: ' + g.complexity + '</span><span>Methods: ' + g.methodCount + '</span><span>Dependencies: ' + g.dependencyCount + '</span></div></div>';
        }).join('');
    }

    function renderDependencies() {
        var s = analyticsData.summary;
        var c = document.getElementById('dependency-list');
        if (!c) return;
        c.innerHTML = '<div class="metrics-grid">' +
            '<div class="metric-card"><div class="metric-header"><span class="metric-label">Total Relationships</span><span class="metric-value" style="color:#ff8800;">' + s.totalEdges + '</span></div><div class="metric-bar"><div class="metric-fill" style="width:100%;background:#ff8800;"></div></div><span class="metric-target">Edges between components</span></div>' +
            '<div class="metric-card"><div class="metric-header"><span class="metric-label">Avg Dependencies</span><span class="metric-value" style="color:#aa66ff;">' + s.averageDependencies.toFixed(2) + '</span></div><div class="metric-bar"><div class="metric-fill" style="width:' + Math.min(100, (s.averageDependencies / 5) * 100) + '%;background:#aa66ff;"></div></div><span class="metric-target">Lower is better (target < 3)</span></div>' +
            '</div>';
    }

    function renderCoverage() {
        var c = analyticsData.coverage;
        var s = analyticsData.summary;
        var overview = document.getElementById('coverage-overview');
        var breakdown = document.getElementById('coverage-breakdown');
        if (overview) {
            var pct = c.overall || 0;
            var color = pct >= 80 ? '#00ff88' : pct >= 50 ? '#ffaa00' : '#ff3355';
            overview.innerHTML = '<div class="coverage-hero"><div class="coverage-ring" style="background:conic-gradient(' + color + ' 0%, ' + color + ' ' + pct + '%, rgba(255,255,255,0.05) ' + pct + '% 100%);"><div class="coverage-inner"><span class="coverage-pct" style="color:' + color + '">' + pct + '%</span><span class="coverage-label">Coverage</span></div></div><div class="coverage-details"><div class="cov-item"><span class="cov-value">' + c.testedCount + '</span><span class="cov-label">Tested</span></div><div class="cov-item"><span class="cov-value">' + c.untestedCount + '</span><span class="cov-label">Untested</span></div><div class="cov-item"><span class="cov-value">' + s.totalTests + '</span><span class="cov-label">Total Tests</span></div></div></div>';
        }
        if (breakdown) {
            var nodes = (analyticsData.architecture && analyticsData.architecture.nodes) || [];
            breakdown.innerHTML = '<div class="cov-table">' + nodes.map(function (n) {
                var tc = n.testCount || 0;
                return '<div class="cov-row"><span class="cov-name">' + n.label + '</span><span class="cov-type" style="color:' + (TYPE_COLORS[n.type] || '#888') + '">' + (TYPE_LABELS[n.type] || n.type) + '</span><span class="cov-badge ' + (tc > 0 ? 'yes' : 'no') + '">' + (tc > 0 ? tc + ' tests' : 'No tests') + '</span><span class="cov-health" style="color:' + ((n.healthScore || 0) >= 0.8 ? '#00ff88' : (n.healthScore || 0) >= 0.5 ? '#ffaa00' : '#ff3355') + '">' + Math.round((n.healthScore || 0) * 100) + '%</span></div>';
            }).join('') + '</div>';
        }
    }

    function renderSuggestions() {
        var items = analyticsData.suggestions || [];
        var container = document.getElementById('suggestions-list');
        if (!container) return;
        if (items.length === 0) {
            container.innerHTML = '<div class="suggestion-empty"><h3>No issues found</h3><p>Your codebase looks healthy!</p></div>';
            return;
        }
        var severityColors = { high: '#ff3355', medium: '#ffaa00', low: '#00ccff' };
        var severityLabels = { high: 'High Priority', medium: 'Medium', low: 'Low' };
        container.innerHTML = items.map(function (item) {
            var sc = severityColors[item.severity] || '#888';
            return '<div class="suggestion-card" style="border-left-color:' + sc + '"><div class="suggestion-header"><span class="suggestion-severity" style="background:' + sc + '">' + (severityLabels[item.severity] || item.severity) + '</span><span class="suggestion-type">' + item.title + '</span></div><div class="suggestion-body"><p>' + item.message + '</p></div><div class="suggestion-footer"><span class="suggestion-component" style="color:' + sc + '">' + item.component + '</span></div></div>';
        }).join('');
    }

    function renderSprintReview() {
        var s = analyticsData.summary;
        var q = analyticsData.quality;
        var suggestions = analyticsData.suggestions || [];
        var summary = document.getElementById('sprint-summary');
        if (summary) {
            var trend = s.averageHealth >= 0.8 ? 'stable and healthy' : s.averageHealth >= 0.5 ? 'needs attention' : 'critical';
            summary.innerHTML = '<div class="sprint-summary"><div class="sprint-stat"><span class="sprint-value">' + s.totalNodes + '</span><span class="sprint-label">Components</span></div><div class="sprint-stat"><span class="sprint-value" style="color:' + (s.averageHealth >= 0.8 ? '#00ff88' : '#ffaa00') + '">' + (s.averageHealth * 100).toFixed(0) + '%</span><span class="sprint-label">Avg Health (' + trend + ')</span></div><div class="sprint-stat"><span class="sprint-value">' + s.godClassCount + '</span><span class="sprint-label">God Classes</span></div><div class="sprint-stat"><span class="sprint-value">' + suggestions.length + '</span><span class="sprint-label">Open Issues</span></div><div class="sprint-stat"><span class="sprint-value">' + s.totalEdges + '</span><span class="sprint-label">Relationships</span></div><div class="sprint-stat"><span class="sprint-value">' + q.averageComplexity + '</span><span class="sprint-label">Avg Complexity</span></div></div>';
        }
        var trajectory = document.getElementById('debt-trajectory');
        if (trajectory) {
            var dl = q.highComplexityCount + s.godClassCount * 2 + q.highDependencyCount;
            var ds = dl <= 2 ? 'Low - Maintainable.' : dl <= 5 ? 'Moderate - Some areas need refactoring.' : 'High - Needs immediate attention.';
            var dc = dl <= 2 ? '#00ff88' : dl <= 5 ? '#ffaa00' : '#ff3355';
            trajectory.innerHTML = '<div class="debt-card"><div class="debt-score" style="color:' + dc + '">' + dl + '</div><div class="debt-label">Technical Debt Score</div><p style="color:#aaa;font-size:13px;margin-top:12px;">' + ds + '</p><div class="debt-bar"><div class="debt-fill" style="width:' + Math.min(100, (dl / 10) * 100) + '%;background:' + dc + '"></div></div></div>';
        }
        var actions = document.getElementById('sprint-actions');
        if (actions) {
            var critical = suggestions.filter(function (s) { return s.severity === 'high'; });
            var html = '';
            if (critical.length > 0) {
                html += '<h4 style="color:#ff3355;margin-bottom:12px;">Critical (' + critical.length + ')</h4>';
                html += critical.slice(0, 5).map(function (item) {
                    return '<div class="action-item"><span class="action-text">' + item.message.slice(0, 120) + '...</span></div>';
                }).join('');
            } else {
                html += '<p class="text-muted">No critical issues. Great work!</p>';
            }
            var med = suggestions.filter(function (s) { return s.severity === 'medium'; });
            if (med.length > 0) {
                html += '<h4 style="color:#ffaa00;margin:16px 0 12px;">Improvements (' + med.length + ')</h4>';
                html += med.slice(0, 3).map(function (item) {
                    return '<div class="action-item"><span class="action-text">' + item.message.slice(0, 120) + '...</span></div>';
                }).join('');
            }
            actions.innerHTML = html;
        }
    }

    function renderArchitectureGraph() {
        var container = document.getElementById('architecture-graph');
        if (!container) return;
        if (network) { network.destroy(); network = null; }
        var gd = analyticsData.architecture;
        if (!gd || !gd.nodes || gd.nodes.length === 0) {
            container.innerHTML = '<div class="panel-loading">No architecture data available.</div>';
            return;
        }
        container.style.cssText = 'width:100%;height:600px;border-radius:8px;overflow:hidden;';
        var nodes = new vis.DataSet((gd.nodes || []).map(function (node) {
            var tc = TYPE_COLORS[node.type] || '#888';
            var h = node.healthScore || 0.5;
            return {
                id: node.id, label: node.label, group: node.type, shape: 'box',
                color: {
                    background: h >= 0.8 ? 'rgba(0,255,136,0.15)' : h >= 0.5 ? 'rgba(255,170,0,0.15)' : 'rgba(255,51,85,0.15)',
                    border: tc, highlight: { background: tc + '33', border: tc }
                },
                font: { size: 12, color: '#ccc', face: 'Inter, sans-serif' },
                borderWidth: 2, margin: 10,
                title: node.label + '<br/>Type: ' + (TYPE_LABELS[node.type] || node.type) + '<br/>Health: ' + Math.round(h * 100) + '%',
            };
        }));
        var edges = new vis.DataSet((gd.edges || []).map(function (edge) {
            return {
                id: edge.id, from: edge.sourceId, to: edge.targetId,
                label: edge.type, color: { color: edge.color || '#888', opacity: 0.5 },
                arrows: { to: { enabled: true, scaleFactor: 0.6 } },
                width: 1.5, font: { size: 10, color: '#666', strokeWidth: 0 },
            };
        }));
        network = new vis.Network(container, { nodes: nodes, edges: edges }, {
            physics: { solver: 'forceAtlas2Based', forceAtlas2Based: { gravitationalConstant: -40, centralGravity: 0.005, springLength: 180, springConstant: 0.03, damping: 0.5 }, stabilization: { iterations: 100 } },
            edges: { smooth: { type: 'curvedCW', roundness: 0.2 } },
            interaction: { hover: true, tooltipDelay: 200, navigationButtons: true, keyboard: true },
        });
    }

    function connectWebSocket() {
        try {
            ws = new WebSocket('ws://' + WS_HOST + ':' + WS_PORT);
            var status = document.getElementById('connection-status');
            if (!status) return;
            ws.onopen = function () {
                var dot = status.querySelector('.status-dot');
                var text = status.querySelector('.status-text');
                if (dot) dot.classList.add('connected');
                if (text) text.textContent = 'Connected';
                ws.send(JSON.stringify({ type: 'subscribe_tests' }));
            };
            ws.onclose = function () {
                var dot = status.querySelector('.status-dot');
                var text = status.querySelector('.status-text');
                if (dot) dot.classList.remove('connected');
                if (text) text.textContent = 'Disconnected';
                setTimeout(connectWebSocket, 3000);
            };
            ws.onmessage = function (event) {
                try {
                    var msg = JSON.parse(event.data);
                    if (msg.type === 'test_update' || msg.type === 'test_batch') {
                        setTimeout(loadAnalytics, 1000);
                    }
                } catch (e) { /* ignore */ }
            };
        } catch (e) {
            setTimeout(connectWebSocket, 3000);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
