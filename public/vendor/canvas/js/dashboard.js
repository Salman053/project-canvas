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
            Object.values(charts).forEach(function (c) { if (c && c.resize) { c.resize(); c.update(); } });
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
            .catch(function () { showLoading(false); });
    }

    function renderAll() {
        if (!analyticsData) return;
        renderSummaryCards();
        renderHealthChart();
        renderTypesChart();
        renderComplexityChart();
        renderHealthTypeChart();
        renderDependencyChart();
        renderTestChart();
        renderQualityMetrics();
        renderGodClasses();
        renderDependencies();
        renderCoverage();
        renderQueryAnalysis();
        renderSuggestions();
        renderSprintReview();
    }

    function showLoading(show) {
        var el = document.getElementById('loading-overlay');
        if (!el) {
            el = document.createElement('div');
            el.id = 'loading-overlay';
            el.innerHTML = '<div class="loader"><div class="loader-ring"></div><div class="loader-text">Loading analytics...</div></div>';
            el.style.cssText = 'position:fixed;inset:0;background:var(--bg-primary);display:flex;align-items:center;justify-content:center;z-index:9999;';
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
                if (tab === 'architecture') setTimeout(renderArchitectureGraph, 150);
                if (tab === 'queries') setTimeout(renderQueryCharts, 150);
            });
        });
    }

    function renderSummaryCards() {
        var s = analyticsData.summary;
        var cards = [
            { label: 'Total Components', value: s.totalNodes, color: '#00ccff', icon: '\u25C6' },
            { label: 'Relationships', value: s.totalEdges, color: '#aa66ff', icon: '\u2261' },
            { label: 'Avg Health', value: (s.averageHealth * 100).toFixed(0) + '%', color: s.averageHealth >= 0.8 ? '#00ff88' : s.averageHealth >= 0.5 ? '#ffaa00' : '#ff3355', icon: '\u2665' },
            { label: 'Healthy / Moderate / Unhealthy', value: s.healthyCount + ' / ' + s.moderateCount + ' / ' + s.unhealthyCount, color: '#888', icon: '\u2630' },
            { label: 'Routes Defined', value: s.routeCount, color: '#8888ff', icon: '\u2192' },
            { label: 'Avg Dependencies', value: s.averageDependencies.toFixed(2), color: '#ff8800', icon: '\u2191' },
            { label: 'God Classes', value: s.godClassCount, color: s.godClassCount > 0 ? '#ff3355' : '#00ff88', icon: '\u26A0' },
            { label: 'Total Suggestions', value: s.suggestionCount, color: s.suggestionCount > 0 ? '#ffaa00' : '#00ff88', icon: '\u270E' },
            { label: 'Total Tests', value: s.totalTests, color: '#66ddaa', icon: '\u2713' },
            { label: 'Coverage', value: (analyticsData.coverage.overall || 0) + '%', color: (analyticsData.coverage.overall || 0) >= 80 ? '#00ff88' : '#ffaa00', icon: '\u25C9' },
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
    function themeSecondary() {
        return document.documentElement.getAttribute('data-theme') === 'light' ? '#555' : '#aaa';
    }
    function themeBg() {
        return document.documentElement.getAttribute('data-theme') === 'light' ? '#f0f2f5' : '#0a0a1a';
    }

    function makeCtx(id) {
        var el = document.getElementById(id);
        return el ? el.getContext('2d') : null;
    }

    function renderHealthChart() {
        var s = analyticsData.summary;
        var ctx = makeCtx('health-chart');
        if (!ctx) return;
        if (charts.health) charts.health.destroy();
        charts.health = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Healthy', 'Moderate', 'Unhealthy'],
                datasets: [{ data: [s.healthyCount, s.moderateCount, s.unhealthyCount], backgroundColor: ['#00ff88', '#ffaa00', '#ff3355'], borderWidth: 0, hoverOffset: 8 }]
            },
            options: {
                responsive: true, maintainAspectRatio: true, aspectRatio: 1.6,
                plugins: { legend: { position: 'bottom', labels: { color: themeText(), padding: 16, font: { size: 12, weight: '500' }, usePointStyle: true, pointStyle: 'circle' } } },
                cutout: '72%',
            }
        });
    }

    function renderTypesChart() {
        var types = analyticsData.performance.nodeTypeCounts || {};
        var ctx = makeCtx('types-chart');
        if (!ctx) return;
        if (charts.types) charts.types.destroy();
        var labels = Object.keys(types).map(function (t) { return TYPE_LABELS[t] || t; });
        var values = Object.values(types);
        var colors = Object.keys(types).map(function (t) { return TYPE_COLORS[t] || '#888'; });
        charts.types = new Chart(ctx, {
            type: 'doughnut',
            data: { labels: labels, datasets: [{ data: values, backgroundColor: colors, borderWidth: 0, hoverOffset: 8 }] },
            options: {
                responsive: true, maintainAspectRatio: true, aspectRatio: 1.6,
                plugins: { legend: { position: 'bottom', labels: { color: themeText(), padding: 12, font: { size: 11 }, usePointStyle: true, pointStyle: 'circle' } } },
                cutout: '55%',
            }
        });
    }

    function renderComplexityChart() {
        var q = analyticsData.quality;
        var ctx = makeCtx('complexity-chart');
        if (!ctx) return;
        if (charts.complexity) charts.complexity.destroy();
        var labels = Object.keys(q.averageComplexityByType).map(function (t) { return TYPE_LABELS[t] || t; });
        var values = Object.values(q.averageComplexityByType);
        var colors = Object.keys(q.averageComplexityByType).map(function (t) { return TYPE_COLORS[t] || '#888'; });
        charts.complexity = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{ label: 'Avg Complexity', data: values, backgroundColor: colors, borderRadius: 6, borderSkipped: false, barPercentage: 0.6 }]
            },
            options: {
                responsive: true, maintainAspectRatio: true, aspectRatio: 1.8,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: function (c) { return 'Complexity: ' + c.raw; } } }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { color: themeText(), font: { size: 11 } }, grid: { color: themeGrid() } },
                    x: { ticks: { color: themeSecondary(), font: { size: 11 } }, grid: { display: false } }
                }
            }
        });
    }

    function renderHealthTypeChart() {
        var q = analyticsData.quality;
        var ctx = makeCtx('health-type-chart');
        if (!ctx) return;
        if (charts.healthType) charts.healthType.destroy();
        var labels = Object.keys(q.averageHealthByType).map(function (t) { return TYPE_LABELS[t] || t; });
        var values = Object.keys(q.averageHealthByType).map(function (t) { return q.averageHealthByType[t] * 100; });
        var colors = values.map(function (v) { return v >= 80 ? '#00ff88' : v >= 50 ? '#ffaa00' : '#ff3355'; });
        charts.healthType = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{ label: 'Health %', data: values, backgroundColor: colors.map(function (c) { return c + '99'; }), borderColor: colors, borderWidth: 2, borderRadius: 6, borderSkipped: false, barPercentage: 0.6 }]
            },
            options: {
                responsive: true, maintainAspectRatio: true, aspectRatio: 1.8,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: function (c) { return 'Health: ' + c.raw + '%'; } } }
                },
                scales: {
                    y: { beginAtZero: true, max: 100, ticks: { color: themeText(), font: { size: 11 }, callback: function (v) { return v + '%'; } }, grid: { color: themeGrid() } },
                    x: { ticks: { color: themeSecondary(), font: { size: 11 } }, grid: { display: false } }
                }
            }
        });
    }

    function renderDependencyChart() {
        var types = analyticsData.performance.edgeTypeCounts || {};
        var ctx = makeCtx('dep-chart');
        if (!ctx) return;
        if (charts.dep) charts.dep.destroy();
        var labels = Object.keys(types);
        var values = Object.values(types);
        var depColors = { relationship: '#00ccff', dependency: '#ff8800', event: '#aa66ff', route: '#66ddaa', test: '#ff66aa' };
        var colors = labels.map(function (l) { return depColors[l] || '#888'; });
        if (labels.length === 0) { labels = ['No edges']; values = [1]; colors = ['#333']; }
        charts.dep = new Chart(ctx, {
            type: 'polarArea',
            data: { labels: labels, datasets: [{ data: values, backgroundColor: colors.map(function (c) { return c + '77'; }), borderColor: colors, borderWidth: 2 }] },
            options: {
                responsive: true, maintainAspectRatio: true, aspectRatio: 1.6,
                plugins: { legend: { position: 'bottom', labels: { color: themeText(), padding: 12, font: { size: 11 }, usePointStyle: true } } },
                scales: { r: { grid: { color: themeGrid() }, ticks: { display: false } } }
            }
        });
    }

    function renderTestChart() {
        var c = analyticsData.coverage;
        var ctx = makeCtx('test-chart');
        if (!ctx) return;
        if (charts.test) charts.test.destroy();
        var pct = c.overall || 0;
        charts.test = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Tested (' + c.testedCount + ')', 'Untested (' + c.untestedCount + ')'],
                datasets: [{ data: [pct, 100 - pct], backgroundColor: ['#66ddaa', 'rgba(255,255,255,0.06)'], borderWidth: 0, hoverOffset: 8 }]
            },
            options: {
                responsive: true, maintainAspectRatio: true, aspectRatio: 1.6,
                cutout: '78%',
                plugins: {
                    legend: { position: 'bottom', labels: { color: themeText(), padding: 12, font: { size: 11, weight: '500' }, usePointStyle: true } },
                    tooltip: { callbacks: { label: function (c) { return c.raw.toFixed(1) + '%'; } } }
                }
            }
        });
    }

    function renderQualityMetrics() {
        var q = analyticsData.quality;
        var s = analyticsData.summary;
        var c = document.getElementById('quality-metrics');
        if (!c) return;
        var metrics = [
            { label: 'Average Complexity', value: q.averageComplexity, max: 20, good: '< 5', color: q.averageComplexity < 5 ? '#00ff88' : q.averageComplexity < 10 ? '#ffaa00' : '#ff3355' },
            { label: 'High Complexity', value: q.highComplexityCount, max: Math.max(1, s.totalNodes), good: '0', color: q.highComplexityCount === 0 ? '#00ff88' : q.highComplexityCount < 3 ? '#ffaa00' : '#ff3355' },
            { label: 'High Dependency', value: q.highDependencyCount, max: Math.max(1, s.totalNodes), good: '0', color: q.highDependencyCount === 0 ? '#00ff88' : q.highDependencyCount < 3 ? '#ffaa00' : '#ff3355' },
            { label: 'God Classes', value: s.godClassCount, max: Math.max(1, s.totalNodes), good: '0', color: s.godClassCount === 0 ? '#00ff88' : s.godClassCount < 2 ? '#ffaa00' : '#ff3355' },
        ];
        c.innerHTML = '<div class="metrics-grid">' + metrics.map(function (m) {
            var pct = Math.min(100, (m.value / m.max) * 100);
            return '<div class="metric-card"><div class="metric-header"><span class="metric-label">' + m.label + '</span><span class="metric-value" style="color:' + m.color + '">' + m.value + '</span></div><div class="metric-bar"><div class="metric-fill" style="width:' + pct + '%;background:' + m.color + '"></div></div><span class="metric-target">Target: ' + m.good + '</span></div>';
        }).join('') + '</div>';
    }

    function renderGodClasses() {
        var gods = analyticsData.quality.godClasses || [];
        var c = document.getElementById('god-classes-list');
        if (!c) return;
        if (gods.length === 0) {
            c.innerHTML = '<p style="padding:24px;text-align:center;color:var(--text-muted);">No god classes detected. Your codebase has a healthy architecture.</p>';
            return;
        }
        c.innerHTML = gods.map(function (g) {
            var score = g.godScore || 0;
            var color = score > 25 ? '#ff3355' : score > 20 ? '#ffaa00' : '#ff8800';
            return '<div class="god-class-item"><div class="god-header"><span style="color:' + color + ';font-weight:700;font-size:14px;">' + g.node.label + '</span><span class="god-score" style="background:' + color + '">Score ' + score.toFixed(1) + '</span></div><div class="god-metrics"><span>Complexity: ' + g.complexity + '</span><span>Methods: ' + g.methodCount + '</span><span>Dependencies: ' + g.dependencyCount + '</span></div></div>';
        }).join('');
    }

    function renderDependencies() {
        var s = analyticsData.summary;
        var c = document.getElementById('dependency-list');
        if (!c) return;
        var avgDep = s.averageDependencies;
        c.innerHTML = '<div class="metrics-grid">' +
            '<div class="metric-card"><div class="metric-header"><span class="metric-label">Total Relationships</span><span class="metric-value" style="color:#ff8800;">' + s.totalEdges + '</span></div><div class="metric-bar"><div class="metric-fill" style="width:100%;background:#ff8800;"></div></div><span class="metric-target">Edges between components</span></div>' +
            '<div class="metric-card"><div class="metric-header"><span class="metric-label">Avg Dependencies</span><span class="metric-value" style="color:' + (avgDep < 3 ? '#00ff88' : avgDep < 5 ? '#ffaa00' : '#ff3355') + ';">' + avgDep.toFixed(2) + '</span></div><div class="metric-bar"><div class="metric-fill" style="width:' + Math.min(100, (avgDep / 8) * 100) + '%;background:' + (avgDep < 3 ? '#00ff88' : avgDep < 5 ? '#ffaa00' : '#ff3355') + ';"></div></div><span class="metric-target">Lower is better (target < 3)</span></div>' +
            '<div class="metric-card"><div class="metric-header"><span class="metric-label">Avg Health</span><span class="metric-value" style="color:' + (s.averageHealth >= 0.8 ? '#00ff88' : s.averageHealth >= 0.5 ? '#ffaa00' : '#ff3355') + ';">' + (s.averageHealth * 100).toFixed(0) + '%</span></div><div class="metric-bar"><div class="metric-fill" style="width:' + (s.averageHealth * 100) + '%;background:' + (s.averageHealth >= 0.8 ? '#00ff88' : s.averageHealth >= 0.5 ? '#ffaa00' : '#ff3355') + ';"></div></div><span class="metric-target">Target > 80%</span></div>' +
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

    function renderQueryAnalysis() {
        var s = analyticsData.suggestions || [];
        var c = document.getElementById('query-analysis');
        if (!c) return;
        var nPlusOne = s.filter(function (item) { return item.icon === 'model' || item.message.indexOf('relationship') > -1 || item.message.indexOf('N+1') > -1 || item.message.indexOf('query') > -1; });
        var container = document.getElementById('query-analysis-list');
        if (!container) return;
        if (nPlusOne.length === 0) {
            container.innerHTML = '<p style="padding:24px;text-align:center;color:var(--text-muted);">No query issues detected. All relationship queries look efficient.</p>';
            return;
        }
        container.innerHTML = nPlusOne.map(function (item) {
            var sc = item.severity === 'high' ? '#ff3355' : item.severity === 'medium' ? '#ffaa00' : '#00ccff';
            return '<div class="suggestion-card" style="border-left-color:' + sc + '"><div class="suggestion-header"><span class="suggestion-severity" style="background:' + sc + '">' + item.severity.toUpperCase() + '</span><span class="suggestion-type">' + item.title + '</span></div><div class="suggestion-body"><p>' + item.message + '</p></div><div class="suggestion-footer"><span class="suggestion-component" style="color:' + sc + '">' + item.component + '</span></div></div>';
        }).join('');
    }

    function renderQueryCharts() {
        var nodes = (analyticsData.architecture && analyticsData.architecture.nodes) || [];
        var ctx = makeCtx('query-chart');
        if (!ctx) return;
        if (charts.query) charts.query.destroy();
        var byType = {};
        nodes.forEach(function (n) {
            byType[n.type] = (byType[n.type] || 0) + 1;
        });
        var labels = Object.keys(byType).map(function (t) { return TYPE_LABELS[t] || t; });
        var values = Object.values(byType);
        var colors = Object.keys(byType).map(function (t) { return TYPE_COLORS[t] || '#888'; });
        charts.query = new Chart(ctx, {
            type: 'bar',
            data: { labels: labels, datasets: [{ label: 'Count', data: values, backgroundColor: colors, borderRadius: 4, borderSkipped: false }] },
            options: {
                responsive: true, maintainAspectRatio: true, aspectRatio: 2,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { color: themeText(), font: { size: 11 } }, grid: { color: themeGrid() } },
                    x: { ticks: { color: themeSecondary(), font: { size: 11 } }, grid: { display: false } }
                }
            }
        });
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
            var healthTrend = s.averageHealth >= 0.8 ? 'Stable & Healthy' : s.averageHealth >= 0.5 ? 'Needs Attention' : 'Critical';
            summary.innerHTML = '<div class="sprint-summary">' +
                '<div class="sprint-stat"><span class="sprint-value">' + s.totalNodes + '</span><span class="sprint-label">Components</span></div>' +
                '<div class="sprint-stat"><span class="sprint-value" style="color:' + (s.averageHealth >= 0.8 ? '#00ff88' : '#ffaa00') + '">' + (s.averageHealth * 100).toFixed(0) + '%</span><span class="sprint-label">Health (' + healthTrend + ')</span></div>' +
                '<div class="sprint-stat"><span class="sprint-value">' + s.godClassCount + '</span><span class="sprint-label">God Classes</span></div>' +
                '<div class="sprint-stat"><span class="sprint-value">' + suggestions.length + '</span><span class="sprint-label">Open Issues</span></div>' +
                '<div class="sprint-stat"><span class="sprint-value">' + s.totalEdges + '</span><span class="sprint-label">Relationships</span></div>' +
                '<div class="sprint-stat"><span class="sprint-value">' + q.averageComplexity + '</span><span class="sprint-label">Avg Complexity</span></div>' +
                '<div class="sprint-stat"><span class="sprint-value">' + s.totalTests + '</span><span class="sprint-label">Total Tests</span></div>' +
                '<div class="sprint-stat"><span class="sprint-value" style="color:' + ((analyticsData.coverage.overall || 0) >= 80 ? '#00ff88' : '#ffaa00') + '">' + (analyticsData.coverage.overall || 0) + '%</span><span class="sprint-label">Coverage</span></div>' +
                '</div>';
        }
        var trajectory = document.getElementById('debt-trajectory');
        if (trajectory) {
            var dl = q.highComplexityCount + s.godClassCount * 2 + q.highDependencyCount;
            var scoreLabel = dl <= 2 ? 'Low - Maintainable. Great shape!' : dl <= 5 ? 'Moderate - Some refactoring needed.' : 'High - Needs immediate attention.';
            var scoreColor = dl <= 2 ? '#00ff88' : dl <= 5 ? '#ffaa00' : '#ff3355';
            trajectory.innerHTML =
                '<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">' +
                '<div class="debt-card"><div class="debt-score" style="color:' + scoreColor + '">' + dl + '</div><div class="debt-label">Technical Debt Score</div><p style="color:var(--text-tertiary);font-size:13px;margin-top:12px;">' + scoreLabel + '</p><div class="debt-bar"><div class="debt-fill" style="width:' + Math.min(100, (dl / 10) * 100) + '%;background:' + scoreColor + '"></div></div></div>' +
                '<div class="debt-card"><div class="debt-score" style="color:' + (s.averageHealth * 100 > 80 ? '#00ff88' : '#ffaa00') + '">' + (s.averageHealth * 100).toFixed(0) + '%</div><div class="debt-label">Overall Health</div><p style="color:var(--text-tertiary);font-size:13px;margin-top:12px;">' + s.healthyCount + ' healthy, ' + s.moderateCount + ' moderate, ' + s.unhealthyCount + ' unhealthy</p><div class="debt-bar"><div class="debt-fill" style="width:' + (s.averageHealth * 100) + '%;background:' + (s.averageHealth >= 0.8 ? '#00ff88' : '#ffaa00') + '"></div></div></div>' +
                '</div>';
        }
        var actions = document.getElementById('sprint-actions');
        if (actions) {
            var groups = { high: [], medium: [], low: [] };
            suggestions.forEach(function (s) { if (groups[s.severity]) groups[s.severity].push(s); });
            var html = '';
            if (groups.high.length > 0) {
                html += '<h4 style="color:#ff3355;margin-bottom:12px;font-size:13px;text-transform:uppercase;letter-spacing:0.5px;">Critical (' + groups.high.length + ')</h4>';
                html += groups.high.slice(0, 5).map(function (item) {
                    return '<div class="action-item"><span class="action-text" style="color:#ff3355;">\u26A0 ' + item.title + '</span><span style="font-size:11px;color:var(--text-muted);display:block;margin-top:4px;">' + item.component + ' \u2014 ' + item.message.slice(0, 150) + '</span></div>';
                }).join('');
            }
            if (groups.medium.length > 0) {
                html += '<h4 style="color:#ffaa00;margin:16px 0 12px;font-size:13px;text-transform:uppercase;letter-spacing:0.5px;">Improvements (' + groups.medium.length + ')</h4>';
                html += groups.medium.slice(0, 5).map(function (item) {
                    return '<div class="action-item"><span class="action-text">' + item.title + '</span><span style="font-size:11px;color:var(--text-muted);display:block;margin-top:4px;">' + item.component + ' \u2014 ' + item.message.slice(0, 150) + '</span></div>';
                }).join('');
            }
            if (html === '') html = '<p style="padding:24px;text-align:center;color:var(--text-muted);">No actions needed. Great code quality!</p>';
            actions.innerHTML = html;
        }
    }

    function renderArchitectureGraph() {
        var container = document.getElementById('architecture-graph');
        if (!container) return;
        if (network) { network.destroy(); network = null; }
        var gd = analyticsData.architecture;
        if (!gd || !gd.nodes || gd.nodes.length === 0) {
            container.innerHTML = '<div style="padding:60px;text-align:center;color:var(--text-muted);">No architecture data available. Run <code>php artisan canvas:scan</code> first.</div>';
            return;
        }
        container.style.cssText = 'width:100%;height:550px;border-radius:8px;overflow:hidden;';

        var edgeColorFn = function (type) {
            return { relationship: '#00ccff', dependency: '#ff8800', event: '#aa66ff', route: '#66ddaa', test: '#ff66aa' }[type] || '#888';
        };

        var shapeFn = function (type) {
            return { model: 'ellipse', controller: 'box', job: 'diamond', listener: 'triangle', policy: 'star', middleware: 'hexagon', provider: 'square', route: 'dot' }[type] || 'dot';
        };

        var nodes = new vis.DataSet((gd.nodes || []).map(function (node) {
            var tc = TYPE_COLORS[node.type] || '#888';
            var h = node.healthScore || 0.5;
            var bgColor = h >= 0.8 ? 'rgba(0,255,136,0.18)' : h >= 0.5 ? 'rgba(255,170,0,0.18)' : 'rgba(255,51,85,0.18)';
            var borderColor = h >= 0.8 ? '#00ff88' : h >= 0.5 ? '#ffaa00' : '#ff3355';
            var size = node.type === 'route' ? 18 : 28;
            return {
                id: node.id, label: node.label, group: node.type,
                shape: shapeFn(node.type),
                size: size,
                color: { background: bgColor, border: borderColor, highlight: { background: tc + '44', border: tc } },
                font: { size: 13, color: '#ddd', face: 'Inter, sans-serif', strokeWidth: 2, strokeColor: themeBg() },
                borderWidth: 2, borderWidthSelected: 3, margin: 12,
                title: '<strong>' + node.label + '</strong><br/>Type: ' + (TYPE_LABELS[node.type] || node.type) + '<br/>Health: ' + Math.round(h * 100) + '%<br/>Complexity: ' + (node.complexityScore || 1),
                nodeData: node,
            };
        }));

        var edges = new vis.DataSet((gd.edges || []).map(function (edge) {
            var eColor = edgeColorFn(edge.type);
            return {
                id: edge.id, from: edge.sourceId, to: edge.targetId,
                label: edge.type,
                color: { color: eColor, opacity: 0.45 },
                arrows: { to: { enabled: true, scaleFactor: 0.7 } },
                width: edge.type === 'test' ? 1.2 : 1.8,
                dashes: edge.type === 'test',
                font: { size: 10, color: '#666', strokeWidth: 0, face: 'Inter, sans-serif' },
            };
        }));

        var options = {
            physics: {
                solver: 'forceAtlas2Based',
                forceAtlas2Based: { gravitationalConstant: -50, centralGravity: 0.004, springLength: 200, springConstant: 0.035, damping: 0.5 },
                stabilization: { iterations: 120 },
            },
            edges: { smooth: { type: 'curvedCW', roundness: 0.15 } },
            interaction: { hover: true, tooltipDelay: 150, navigationButtons: true, keyboard: true, zoomView: true },
            nodes: { shadow: { enabled: true, color: 'rgba(0,0,0,0.3)', size: 6 } },
        };

        network = new vis.Network(container, { nodes: nodes, edges: edges }, options);

        network.on('click', function (params) {
            if (params.nodes.length > 0) {
                var nodeId = params.nodes[0];
                network.focus(nodeId, { scale: 1.8, animation: true });
            }
        });

        network.on('stabilized', function () {
            network.fit({ animation: true });
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
