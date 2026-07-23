(function () {
    'use strict';

    const config = window.CANVAS_CONFIG || {};
    const API = config.apiBase || '/api/canvas';
    const WS_HOST = config.wsHost || '127.0.0.1';
    const WS_PORT = config.wsPort || '8081';

    let network, nodesDataSet, edgesDataSet, graphData, selectedNodeId;
    let ws = null;

    const TYPE_CONFIG = {
        model:     { color: '#00ccff', shape: 'ellipse',   label: 'Model' },
        controller:{ color: '#ff8800', shape: 'box',       label: 'Controller' },
        job:       { color: '#aa66ff', shape: 'diamond',   label: 'Job' },
        listener:  { color: '#66ddaa', shape: 'triangle',  label: 'Listener' },
        policy:    { color: '#ff66aa', shape: 'star',      label: 'Policy' },
        middleware:{ color: '#ffaa00', shape: 'hexagon',   label: 'Middleware' },
        provider:  { color: '#ff3355', shape: 'square',    label: 'Provider' },
        route:     { color: '#8888ff', shape: 'dot',       label: 'Route' },
    };

    const EDGE_TYPE_COLORS = {
        relationship: '#00ccff',
        dependency:   '#ff8800',
        event:        '#aa66ff',
        route:        '#66ddaa',
        test:         '#ff66aa',
    };

    function init() {
        const container = document.getElementById('canvas-container');
        container.innerHTML = '';

        const loading = document.getElementById('loading-overlay');
        if (loading) loading.classList.remove('hidden');

        nodesDataSet = new vis.DataSet([]);
        edgesDataSet = new vis.DataSet([]);

        const options = {
            physics: {
                solver: 'forceAtlas2Based',
                forceAtlas2Based: {
                    gravitationalConstant: -60,
                    centralGravity: 0.005,
                    springLength: 200,
                    springConstant: 0.04,
                    damping: 0.5,
                },
                stabilization: { iterations: 150 },
            },
            layout: {
                improvedLayout: true,
            },
            edges: {
                smooth: { type: 'curvedCW', roundness: 0.2 },
                arrows: { to: { enabled: true, scaleFactor: 0.8 } },
                font: { size: 11, color: '#888', face: 'Inter, sans-serif' },
                color: { inherit: false, opacity: 0.5 },
                width: 1.5,
            },
            nodes: {
                font: {
                    size: 13,
                    color: '#e0e0e0',
                    face: 'Inter, sans-serif',
                    strokeWidth: 2,
                    strokeColor: '#0a0a1a',
                },
                borderWidth: 2,
                shadow: {
                    enabled: true,
                    color: 'rgba(0,0,0,0.4)',
                    size: 8,
                },
                margin: 12,
            },
            interaction: {
                hover: true,
                tooltipDelay: 200,
                navigationButtons: true,
                keyboard: true,
            },
            manipulation: { enabled: false },
        };

        network = new vis.Network(container, { nodes: nodesDataSet, edges: edgesDataSet }, options);

        network.on('click', function (params) {
            if (params.nodes.length > 0) {
                const nodeId = params.nodes[0];
                controls.autoRotate = false;
                openNodePanel(nodeId);
            } else {
                closeNodePanel();
            }
        });

        network.on('deselectNode', function () {
            closeNodePanel();
        });

        network.on('hoverNode', function (params) {
            const nodeId = params.node;
            const node = nodesDataSet.get(nodeId);
            if (node) {
                const edges = edgesDataSet.get({
                    filter: function (e) {
                        return e.from === nodeId || e.to === nodeId;
                    }
                });
                edges.forEach(function (e) {
                    edgesDataSet.update({
                        id: e.id,
                        color: { color: e.originalColor || e.color.color, opacity: 1.0 },
                        width: 2.5,
                    });
                });
            }
        });

        network.on('blurNode', function (params) {
            const nodeId = params.node;
            const edges = edgesDataSet.get({
                filter: function (e) {
                    return e.from === nodeId || e.to === nodeId;
                }
            });
            edges.forEach(function (e) {
                edgesDataSet.update({
                    id: e.id,
                    color: { color: e.originalColor || e.color.color, opacity: 0.4 },
                    width: 1.5,
                });
            });
        });

        network.on('stabilized', function () {
            const loading = document.getElementById('loading-overlay');
            if (loading) loading.classList.add('hidden');
        });

        loadGraphData();
        setupUI();
        connectWebSocket();
        window.addEventListener('resize', function () {
            network.fit({ animation: true });
        });
    }

    function loadGraphData() {
        fetch(API + '/graph')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                graphData = data;
                buildGraph(data);
                updateStats(data);
            })
            .catch(function (err) {
                console.error('Failed to load graph data:', err);
                var loader = document.querySelector('.loader-text');
                if (loader) loader.textContent = 'Failed to load graph. Retrying...';
                setTimeout(loadGraphData, 3000);
            });
    }

    function buildGraph(data) {
        var nodes = data.nodes || [];
        var edges = data.edges || [];

        if (nodes.length === 0) {
            var loader = document.querySelector('.loader-text');
            if (loader) loader.textContent = 'No components found. Run php artisan canvas:scan first.';
            return;
        }

        var visNodes = nodes.map(function (node) {
            var tc = TYPE_CONFIG[node.type] || { color: '#888', shape: 'dot', label: node.type };
            var health = node.healthScore || 0.5;
            var healthColor = health >= 0.8 ? '#00ff88' : health >= 0.5 ? '#ffaa00' : '#ff3355';
            var borderColor = node.type === 'route' ? 'rgba(136,136,255,0.3)' : tc.color;

            return {
                id: node.id,
                label: node.label,
                title: '<strong>' + node.label + '</strong><br/>' +
                       'Type: ' + tc.label + '<br/>' +
                       'Health: ' + (health * 100).toFixed(0) + '%' + '<br/>' +
                       'Complexity: ' + (node.complexityScore || 1) + '<br/>' +
                       'Dependencies: ' + (node.dependencyCount || 0),
                group: node.type,
                shape: tc.shape,
                color: {
                    background: healthColor + '33',
                    border: borderColor,
                    highlight: { background: healthColor + '66', border: healthColor },
                    hover: { background: healthColor + '44', border: healthColor },
                },
                borderWidth: 2,
                borderWidthSelected: 3,
                size: node.type === 'route' ? 15 : 25,
                value: node.dependencyCount || 1,
                nodeData: node,
            };
        });

        var visEdges = edges.map(function (edge) {
            var eColor = edge.color || EDGE_TYPE_COLORS[edge.type] || '#888';
            return {
                id: edge.id,
                from: edge.sourceId,
                to: edge.targetId,
                label: edge.label || edge.type,
                color: { color: eColor, opacity: 0.4 },
                originalColor: eColor,
                arrows: { to: { enabled: true, scaleFactor: 0.6 } },
                dashes: edge.type === 'test',
                width: edge.type === 'test' ? 1 : 1.5,
                edgeData: edge,
            };
        });

        nodesDataSet.clear();
        edgesDataSet.clear();
        nodesDataSet.add(visNodes);
        edgesDataSet.add(visEdges);

        var hidden = document.getElementById('loading-overlay');
        if (hidden) setTimeout(function () { hidden.classList.add('hidden'); }, 500);
    }

    function updateStats(data) {
        var nodes = data.nodes || [];
        var edges = data.edges || [];
        var health = data.averageHealth || 0;

        var el = document.getElementById('stat-nodes');
        if (el) el.textContent = nodes.length;

        el = document.getElementById('stat-edges');
        if (el) el.textContent = edges.length;

        el = document.getElementById('stat-health');
        if (el) el.textContent = Math.round(health * 100) + '%';

        var testEdges = edges.filter(function (e) { return e.type === 'test'; });
        el = document.getElementById('stat-tests');
        if (el) el.textContent = testEdges.length;
    }

    function openNodePanel(nodeId) {
        selectedNodeId = nodeId;
        var panel = document.getElementById('side-panel');
        var body = document.getElementById('panel-body');
        if (!panel || !body) return;

        panel.classList.add('open');
        body.innerHTML = '<div class="panel-loading">Loading component details...</div>';

        network.selectNodes([nodeId], false);

        fetch(API + '/graph/node/' + encodeURIComponent(nodeId))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                renderNodePanel(data);
            })
            .catch(function () {
                body.innerHTML = '<div class="panel-loading">Failed to load details.</div>';
            });
    }

    function closeNodePanel() {
        var panel = document.getElementById('side-panel');
        if (panel) panel.classList.remove('open');
        if (selectedNodeId) {
            network.unselectAll();
            selectedNodeId = null;
        }
    }

    function renderNodePanel(data) {
        var node = data.node;
        if (!node) return;
        var body = document.getElementById('panel-body');
        if (!body) return;

        var title = document.getElementById('panel-title');
        if (title) title.textContent = node.label;

        var tc = TYPE_CONFIG[node.type] || { color: '#888', label: node.type };
        var healthColor = (node.healthScore || 0) >= 0.8 ? '#00ff88' :
                          (node.healthScore || 0) >= 0.5 ? '#ffaa00' : '#ff3355';

        var html = '';
        html += '<div class="node-info-grid">';
        html += '<div class="node-info-item"><div class="value" style="color:' + tc.color + '">' + tc.label + '</div><div class="label">Type</div></div>';
        html += '<div class="node-info-item"><div class="value" style="color:' + healthColor + '">' + Math.round((node.healthScore || 0) * 100) + '%</div><div class="label">Health</div></div>';
        html += '<div class="node-info-item"><div class="value">' + (node.complexityScore || 1) + '</div><div class="label">Complexity</div></div>';
        html += '<div class="node-info-item"><div class="value">' + (node.testCount || 0) + '</div><div class="label">Tests</div></div>';
        html += '<div class="node-info-item"><div class="value">' + (node.dependencyCount || 0) + '</div><div class="label">Dependencies</div></div>';
        html += '<div class="node-info-item"><div class="value">' + (node.dependentCount || 0) + '</div><div class="label">Dependents</div></div>';
        html += '</div>';

        if (node.namespace) {
            html += '<div class="panel-section"><h4>Namespace</h4><code style="font-size:12px;color:#888;word-break:break-all;">' + node.namespace + '</code></div>';
        }

        if (node.filePath) {
            html += '<div class="panel-section"><h4>File</h4><code style="font-size:12px;color:#666;word-break:break-all;">' + node.filePath + '</code></div>';
        }

        if (node.metadata && Object.keys(node.metadata).length > 0) {
            html += '<div class="panel-section"><h4>Metadata</h4><div class="metadata-list">';
            for (var key in node.metadata) {
                var val = node.metadata[key];
                var str = typeof val === 'object' ? JSON.stringify(val) : String(val);
                html += '<div class="metadata-item"><span class="meta-key">' + key + '</span><span class="meta-value">' + (str.length > 120 ? str.slice(0, 120) + '...' : str) + '</span></div>';
            }
            html += '</div></div>';
        }

        if (data.relatedNodes && data.relatedNodes.length > 0) {
            html += '<div class="panel-section"><h4>Connected Components (' + data.relatedNodes.length + ')</h4><div class="edge-list">';
            data.relatedNodes.forEach(function (rn) {
                var rc = TYPE_CONFIG[rn.type] || { color: '#888' };
                html += '<div class="edge-item" data-id="' + rn.id + '" style="cursor:pointer;"><div class="edge-color" style="background:' + rc.color + '"></div><span class="edge-label">' + rn.label + '</span><span style="font-size:10px;color:#555;">' + rn.type + '</span></div>';
            });
            html += '</div></div>';

            setTimeout(function () {
                body.querySelectorAll('.edge-item[data-id]').forEach(function (el) {
                    el.addEventListener('click', function () {
                        var id = this.dataset.id;
                        if (id) {
                            network.focus(id, { scale: 1.5, animation: true });
                            openNodePanel(id);
                        }
                    });
                });
            }, 50);
        }

        if (data.edges && data.edges.length > 0) {
            var tests = data.edges.filter(function (e) { return e.type === 'test'; });
            if (tests.length > 0) {
                html += '<div class="panel-section"><h4>Tests (' + tests.length + ')</h4>';
                tests.forEach(function (t) {
                    html += '<div class="test-result passed">\u2713 ' + (t.label || 'Test') + '</div>';
                });
                html += '</div>';
            }

            var routes = data.edges.filter(function (e) { return e.type === 'route'; });
            if (routes.length > 0) {
                html += '<div class="panel-section"><h4>Routes (' + routes.length + ')</h4>';
                routes.forEach(function (r) {
                    html += '<div class="edge-item"><div class="edge-color" style="background:#66ddaa"></div><span class="edge-label" style="font-family:monospace;font-size:11px;">' + (r.label || r.id) + '</span></div>';
                });
                html += '</div>';
            }
        }

        if (data.sourceCode) {
            html += '<div class="panel-section"><h4>Source Code</h4><pre class="source-code">' + escapeHtml(data.sourceCode.slice(0, 4000)) + '</pre></div>';
        }

        body.innerHTML = html;
    }

    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function setupUI() {
        var closeBtn = document.getElementById('panel-close');
        if (closeBtn) closeBtn.addEventListener('click', closeNodePanel);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeNodePanel();
        });

        document.querySelectorAll('[data-filter]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var type = this.dataset.filter;
                document.querySelectorAll('[data-filter]').forEach(function (b) { b.classList.remove('active'); });
                this.classList.add('active');
                filterNodes(type);
            });
        });

        var heatmapBtn = document.getElementById('btn-heatmap');
        if (heatmapBtn) heatmapBtn.addEventListener('click', toggleHeatmap);

        var snapshotBtn = document.getElementById('btn-snapshot');
        if (snapshotBtn) snapshotBtn.addEventListener('click', takeSnapshot);

        var exportBtn = document.getElementById('btn-export');
        if (exportBtn) exportBtn.addEventListener('click', exportGraph);

        var dashBtn = document.getElementById('btn-dashboard');
        if (dashBtn) {
            dashBtn.addEventListener('click', function () {
                window.location.href = '/canvas/dashboard';
            });
        }

        var searchInput = document.getElementById('search-input');
        var searchResults = document.getElementById('search-results');
        var searchTimeout;

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                clearTimeout(searchTimeout);
                var q = this.value.trim();
                if (q.length < 1) {
                    if (searchResults) searchResults.classList.add('hidden');
                    return;
                }
                searchTimeout = setTimeout(function () {
                    fetch(API + '/search?q=' + encodeURIComponent(q))
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (!searchResults) return;
                            if (data.results && data.results.length > 0) {
                                searchResults.innerHTML = data.results.map(function (r) {
                                    var tc = TYPE_CONFIG[r.type] || { color: '#888', label: r.type };
                                    return '<div class="search-result-item" data-id="' + r.id + '">' +
                                        '<span class="type-badge" style="background:' + tc.color + '22;color:' + tc.color + '">' + tc.label + '</span>' +
                                        '<span class="name">' + r.label + '</span>' +
                                        '<span style="margin-left:auto;font-size:11px;color:#555;">' + Math.round((r.healthScore || 0) * 100) + '%</span>' +
                                        '</div>';
                                }).join('');
                                searchResults.classList.remove('hidden');
                                searchResults.querySelectorAll('.search-result-item').forEach(function (el) {
                                    el.addEventListener('click', function () {
                                        var id = this.dataset.id;
                                        if (id && network) {
                                            network.focus(id, { scale: 1.5, animation: true });
                                            openNodePanel(id);
                                        }
                                        searchResults.classList.add('hidden');
                                        if (searchInput) searchInput.value = '';
                                    });
                                });
                            } else {
                                searchResults.innerHTML = '<div style="padding:16px;text-align:center;color:#555;">No results found</div>';
                                searchResults.classList.remove('hidden');
                            }
                        });
                }, 200);
            });
        }

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.search-box') && searchResults) {
                searchResults.classList.add('hidden');
            }
        });

        setupThemeToggle();
        setupLegend();
    }

    function setupThemeToggle() {
        var btn = document.getElementById('graph-theme-toggle');
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

            var bg = next === 'light' ? '#f0f2f5' : '#0a0a1a';
            var nodeColor = next === 'light' ? '#1a1a2e' : '#e0e0e0';
            var edgeColor = next === 'light' ? '#666' : '#888';
            var bgRgba = next === 'light' ? '#f0f2f5' : '#0a0a1a';

            network.setOptions({
                nodes: { font: { color: nodeColor, strokeColor: bg } },
                edges: { font: { color: edgeColor } }
            });

            var allNodes = nodesDataSet.get();
            allNodes.forEach(function (n) {
                nodesDataSet.update({ id: n.id, font: { color: nodeColor, strokeColor: bg } });
            });
            edgesDataSet.get().forEach(function (e) {
                edgesDataSet.update({ id: e.id, font: { color: edgeColor } });
            });
        });
    }

    function setupLegend() {
        var toolbar = document.querySelector('.toolbar-nav');
        if (!toolbar) return;

        var legendDiv = document.createElement('div');
        legendDiv.className = 'legend';
        legendDiv.style.cssText = 'display:flex;align-items:center;gap:10px;margin-left:8px;padding-left:8px;border-left:1px solid rgba(255,255,255,0.08);';

        var sorted = Object.keys(TYPE_CONFIG).sort();
        sorted.forEach(function (type) {
            var tc = TYPE_CONFIG[type];
            var item = document.createElement('span');
            item.style.cssText = 'display:flex;align-items:center;gap:4px;font-size:10px;color:#666;cursor:pointer;padding:2px 6px;border-radius:4px;transition:all 0.15s;';
            item.innerHTML = '<span style="width:8px;height:8px;border-radius:50%;background:' + tc.color + ';display:inline-block;"></span>' + tc.label;
            item.addEventListener('click', function () {
                var filterBtn = document.querySelector('[data-filter="' + type + '"]');
                if (filterBtn) filterBtn.click();
            });
            item.addEventListener('mouseenter', function () { this.style.background = 'rgba(255,255,255,0.05)'; this.style.color = '#ccc'; });
            item.addEventListener('mouseleave', function () { this.style.background = 'transparent'; this.style.color = '#666'; });
            legendDiv.appendChild(item);
        });

        toolbar.appendChild(legendDiv);
    }

    function filterNodes(type) {
        if (!graphData) return;

        var allNodes = nodesDataSet.get();
        if (type === 'all') {
            nodesDataSet.update(allNodes.map(function (n) { return { id: n.id, hidden: false }; }));
            edgesDataSet.get().forEach(function (e) {
                edgesDataSet.update({ id: e.id, hidden: false });
            });
            return;
        }

        var visibleIds = {};
        graphData.nodes.forEach(function (n) {
            if (n.type === type) visibleIds[n.id] = true;
        });

        allNodes.forEach(function (n) {
            nodesDataSet.update({ id: n.id, hidden: !visibleIds[n.id] });
        });

        var allEdges = edgesDataSet.get();
        allEdges.forEach(function (e) {
            var show = visibleIds[e.from] && visibleIds[e.to];
            edgesDataSet.update({ id: e.id, hidden: !show });
        });
    }

    function toggleHeatmap() {
        if (!graphData) return;
        var btn = document.getElementById('btn-heatmap');
        if (!btn) return;

        var active = btn.classList.toggle('active');

        if (active) {
            fetch(API + '/heatmap')
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var heat = data.commitHeatmap || {};
                    graphData.nodes.forEach(function (node) {
                        var commits = heat[node.filePath] || 0;
                        var intensity = Math.min(1, commits / 10);
                        var hue = 0.05 - intensity * 0.05;
                        var bgColor = hslToHex(hue, 0.9, 0.5);
                        nodesDataSet.update({
                            id: node.id,
                            color: {
                                background: hslToHex(hue, 0.9, 0.3),
                                border: bgColor,
                                highlight: { background: hslToHex(hue, 0.9, 0.4), border: bgColor },
                                hover: { background: hslToHex(hue, 0.9, 0.35), border: bgColor },
                            }
                        });
                    });
                });
        } else {
            buildGraph(graphData);
        }
    }

    function takeSnapshot() {
        fetch(API + '/snapshot', { method: 'POST' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                showNotification('Snapshot taken: ' + (data.snapshotId || '').slice(0, 8) + '...', true);
            })
            .catch(function () {
                showNotification('Snapshot failed', false);
            });
    }

    function exportGraph() {
        var json = JSON.stringify(graphData, null, 2);
        var blob = new Blob([json], { type: 'application/json' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'canvas-architecture-' + new Date().toISOString().slice(0, 10) + '.json';
        a.click();
        URL.revokeObjectURL(url);
        showNotification('Architecture exported', true);
    }

    function showNotification(message, success) {
        var container = document.getElementById('test-notification-container');
        if (!container) return;
        var notif = document.createElement('div');
        notif.className = 'test-notification ' + (success ? 'passed' : 'failed');
        notif.textContent = (success ? '\u2713' : '\u2717') + ' ' + message;
        container.appendChild(notif);
        setTimeout(function () {
            notif.style.opacity = '0';
            notif.style.transform = 'translateX(50px)';
            notif.style.transition = 'all 0.3s ease';
            setTimeout(function () { notif.remove(); }, 300);
        }, 3000);
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
                    handleWsMessage(msg);
                } catch (e) { /* ignore */ }
            };
        } catch (e) {
            setTimeout(connectWebSocket, 3000);
        }
    }

    function handleWsMessage(msg) {
        switch (msg.type) {
            case 'test_update':
                handleTestUpdate(msg);
                break;
            case 'test_batch':
                msg.results.forEach(function (r) { handleTestUpdate(r); });
                break;
            case 'graph_update':
                if (msg.graph) loadGraphData();
                break;
        }
    }

    function handleTestUpdate(msg) {
        var passed = msg.passed;
        var componentId = msg.componentId;
        var testName = msg.testName || 'Test';

        showNotification(testName, passed);

        if (componentId && nodesDataSet.get(componentId)) {
            animateNodePulse(componentId, passed);
        }
    }

    function animateNodePulse(nodeId, passed) {
        var node = nodesDataSet.get(nodeId);
        if (!node) return;

        var flashColor = passed ? '#00ff88' : '#ff3355';
        var count = 0;
        var maxFlashes = 3;

        function flash() {
            if (count >= maxFlashes) {
                var health = (node.nodeData && node.nodeData.healthScore) || 0.5;
                var healthColor = health >= 0.8 ? '#00ff88' : health >= 0.5 ? '#ffaa00' : '#ff3355';
                nodesDataSet.update({
                    id: nodeId,
                    color: {
                        background: healthColor + '33',
                        border: healthColor,
                        highlight: { background: healthColor + '66', border: healthColor },
                        hover: { background: healthColor + '44', border: healthColor },
                    }
                });
                return;
            }
            var bg = count % 2 === 0 ? flashColor + '66' : flashColor + '22';
            nodesDataSet.update({
                id: nodeId,
                color: { background: bg, border: flashColor }
            });
            count++;
            setTimeout(flash, 200);
        }
        flash();
    }

    function hslToHex(h, s, l) {
        h = ((h % 1) + 1) % 1;
        var r, g, b;
        if (s === 0) {
            r = g = b = l;
        } else {
            var hue2rgb = function (p, q, t) {
                if (t < 0) t += 1;
                if (t > 1) t -= 1;
                if (t < 1 / 6) return p + (q - p) * 6 * t;
                if (t < 1 / 2) return q;
                if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
                return p;
            };
            var q = l < 0.5 ? l * (1 + s) : l + s - l * s;
            var p = 2 * l - q;
            r = hue2rgb(p, q, h + 1 / 3);
            g = hue2rgb(p, q, h);
            b = hue2rgb(p, q, h - 1 / 3);
        }
        function toHex(x) { var hex = Math.round(x * 255).toString(16); return hex.length === 1 ? '0' + hex : hex; }
        return '#' + toHex(r) + toHex(g) + toHex(b);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
