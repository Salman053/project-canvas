(function () {
    'use strict';

    const config = window.CANVAS_CONFIG || {};
    const API = config.apiBase || '/api/canvas';
    const WS_HOST = config.wsHost || '127.0.0.1';
    const WS_PORT = config.wsPort || '8081';
    const PARTICLE_COUNT = config.particleCount || 2000;
    const NODE_SPACING = config.nodeSpacing || 8;
    const ANIMATION_SPEED = config.animationSpeed || 1;
    const BG_COLOR = new THREE.Color(config.bgColor || '#0a0a1a');

    let scene, camera, renderer, controls, graphData, nodeMeshMap, edgeLines, particles;
    let clock = new THREE.Clock();
    let ws = null;
    let selectedNodeId = null;
    let animationQueue = [];
    let hoveredNode = null;
    let heatmapMode = false;

    const TYPE_MATERIALS = {};
    const TYPE_COLORS = {
        model: '#00ccff', controller: '#ff8800', job: '#aa66ff',
        listener: '#66ddaa', policy: '#ff66aa', middleware: '#ffaa00',
        provider: '#ff3355', route: '#8888ff'
    };
    const TYPE_SHAPES = {
        model: 'box', controller: 'cone', job: 'sphere',
        listener: 'diamond', policy: 'star', middleware: 'hexagon',
        provider: 'octagon', route: 'ring'
    };
    const EDGE_COLORS = {
        relationship: '#00ccff', dependency: '#ff8800',
        event: '#aa66ff', route: '#66ddaa', test: '#ff66aa'
    };

    function init() {
        scene = new THREE.Scene();
        scene.background = BG_COLOR;
        scene.fog = new THREE.Fog(BG_COLOR, 80, 200);

        camera = new THREE.PerspectiveCamera(60, window.innerWidth / window.innerHeight, 0.1, 500);
        camera.position.set(40, 30, 60);

        renderer = new THREE.WebGLRenderer({ antialias: true, alpha: false });
        renderer.setSize(window.innerWidth, window.innerHeight);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        renderer.shadowMap.enabled = true;
        renderer.shadowMap.type = THREE.PCFSoftShadowMap;
        renderer.toneMapping = THREE.ACESFilmicToneMapping;
        renderer.toneMappingExposure = 1.2;
        document.getElementById('canvas-container').appendChild(renderer.domElement);

        controls = new THREE.OrbitControls(camera, renderer.domElement);
        controls.enableDamping = true;
        controls.dampingFactor = 0.08;
        controls.minDistance = 10;
        controls.maxDistance = 200;
        controls.autoRotate = true;
        controls.autoRotateSpeed = 0.4;

        createLights();
        createStarfield();
        loadGraphData();

        window.addEventListener('resize', onResize);
        setupUI();
        setupRaycaster();
        connectWebSocket();
    }

    function createLights() {
        const ambient = new THREE.AmbientLight(0x222244, 0.6);
        scene.add(ambient);

        const dirLight = new THREE.DirectionalLight(0xffffff, 1.5);
        dirLight.position.set(50, 80, 50);
        dirLight.castShadow = true;
        scene.add(dirLight);

        const fillLight = new THREE.DirectionalLight(0x0088ff, 0.4);
        fillLight.position.set(-50, 0, -50);
        scene.add(fillLight);

        const rimLight = new THREE.DirectionalLight(0x00ff88, 0.3);
        rimLight.position.set(0, -50, 0);
        scene.add(rimLight);
    }

    function createStarfield() {
        const geometry = new THREE.BufferGeometry();
        const positions = new Float32Array(PARTICLE_COUNT * 3);
        const sizes = new Float32Array(PARTICLE_COUNT);
        const colors = new Float32Array(PARTICLE_COUNT * 3);

        for (let i = 0; i < PARTICLE_COUNT; i++) {
            positions[i * 3] = (Math.random() - 0.5) * 400;
            positions[i * 3 + 1] = (Math.random() - 0.5) * 400;
            positions[i * 3 + 2] = (Math.random() - 0.5) * 400;
            sizes[i] = Math.random() * 2 + 0.5;
            const c = new THREE.Color().setHSL(0.6, 0.5, Math.random() * 0.3 + 0.1);
            colors[i * 3] = c.r; colors[i * 3 + 1] = c.g; colors[i * 3 + 2] = c.b;
        }

        geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
        geometry.setAttribute('size', new THREE.BufferAttribute(sizes, 1));
        geometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));

        const material = new THREE.PointsMaterial({
            size: 0.8, vertexColors: true, transparent: true,
            opacity: 0.8, blending: THREE.AdditiveBlending,
            sizeAttenuation: true
        });

        particles = new THREE.Points(geometry, material);
        scene.add(particles);
    }

    function loadGraphData() {
        fetch(API + '/graph')
            .then(r => r.json())
            .then(data => {
                graphData = data;
                buildGraph(data);
                updateStats(data);
                document.getElementById('loading-overlay').classList.add('hidden');
                setTimeout(() => controls.autoRotate = true, 2000);
            })
            .catch(err => {
                console.error('Failed to load graph data:', err);
                document.querySelector('.loader-text').textContent = 'Failed to load graph. Retrying...';
                setTimeout(loadGraphData, 3000);
            });
    }

    function buildGraph(data) {
        const nodes = data.nodes || [];
        const edges = data.edges || [];

        if (nodes.length === 0) {
            document.querySelector('.loader-text').textContent = 'No components found. Run php artisan canvas:scan first.';
            return;
        }

        const positions = computeForceLayout(nodes, edges);
        nodeMeshMap = {};
        const nodeGroup = new THREE.Group();

        nodes.forEach((node, i) => {
            const pos = positions[i] || { x: 0, y: 0, z: 0 };
            const mesh = createNodeMesh(node, pos);
            mesh.userData.nodeId = node.id;
            mesh.userData.nodeData = node;
            nodeGroup.add(mesh);
            nodeMeshMap[node.id] = mesh;
        });

        scene.add(nodeGroup);

        edgeLines = new THREE.Group();
        edges.forEach(edge => {
            const source = nodeMeshMap[edge.sourceId];
            const target = nodeMeshMap[edge.targetId];
            if (source && target) {
                const line = createEdgeLine(source.position, target.position, edge);
                edgeLines.add(line);
            }
        });
        scene.add(edgeLines);
    }

    function computeForceLayout(nodes, edges) {
        const positions = nodes.map(() => ({
            x: (Math.random() - 0.5) * 30,
            y: (Math.random() - 0.5) * 20,
            z: (Math.random() - 0.5) * 30
        }));

        const velocity = nodes.map(() => ({ x: 0, y: 0, z: 0 }));
        const edgeMap = {};
        edges.forEach(e => {
            const sidx = nodes.findIndex(n => n.id === e.sourceId);
            const tidx = nodes.findIndex(n => n.id === e.targetId);
            if (sidx >= 0 && tidx >= 0) {
                if (!edgeMap[sidx]) edgeMap[sidx] = [];
                edgeMap[sidx].push(tidx);
                if (!edgeMap[tidx]) edgeMap[tidx] = [];
                edgeMap[tidx].push(sidx);
            }
        });

        for (let iter = 0; iter < 100; iter++) {
            const cooling = 1 - iter / 100;

            nodes.forEach((a, i) => {
                nodes.forEach((b, j) => {
                    if (i === j) return;
                    const dx = positions[j].x - positions[i].x;
                    const dy = positions[j].y - positions[i].y;
                    const dz = positions[j].z - positions[i].z;
                    const dist = Math.sqrt(dx * dx + dy * dy + dz * dz) || 1;
                    const force = NODE_SPACING * NODE_SPACING / (dist * dist);
                    const fx = dx / dist * force;
                    const fy = dy / dist * force;
                    const fz = dz / dist * force;
                    velocity[i].x -= fx; velocity[i].y -= fy; velocity[i].z -= fz;
                    velocity[j].x += fx; velocity[j].y += fy; velocity[j].z += fz;
                });

                if (edgeMap[i]) {
                    edgeMap[i].forEach(j => {
                        const dx = positions[j].x - positions[i].x;
                        const dy = positions[j].y - positions[i].y;
                        const dz = positions[j].z - positions[i].z;
                        const dist = Math.sqrt(dx * dx + dy * dy + dz * dz) || 1;
                        const force = dist * 0.05;
                        velocity[i].x += dx * force; velocity[i].y += dy * force; velocity[i].z += dz * force;
                    });
                }
            });

            nodes.forEach((_, i) => {
                velocity[i].x *= 0.85; velocity[i].y *= 0.85; velocity[i].z *= 0.85;
                positions[i].x += velocity[i].x * cooling;
                positions[i].y += velocity[i].y * cooling;
                positions[i].z += velocity[i].z * cooling;
                const maxDim = 40;
                positions[i].x = Math.max(-maxDim, Math.min(maxDim, positions[i].x));
                positions[i].y = Math.max(-maxDim, Math.min(maxDim, positions[i].y));
                positions[i].z = Math.max(-maxDim, Math.min(maxDim, positions[i].z));
            });
        }

        const typeCenters = {};
        nodes.forEach((node, i) => {
            if (!typeCenters[node.type]) typeCenters[node.type] = [];
            typeCenters[node.type].push(positions[i]);
        });
        Object.keys(typeCenters).forEach(type => {
            const pts = typeCenters[type];
            const cx = pts.reduce((s, p) => s + p.x, 0) / pts.length;
            const cy = pts.reduce((s, p) => s + p.y, 0) / pts.length;
            const cz = pts.reduce((s, p) => s + p.z, 0) / pts.length;
            pts.forEach(p => {
                p.x += (cx - p.x) * 0.1;
                p.y += (cy - p.y) * 0.1;
                p.z += (cz - p.z) * 0.1;
            });
        });

        nodes.forEach((node, i) => {
            const health = node.healthScore || 0.5;
            if (health >= 0.8) {
                positions[i].y += 2;
            } else if (health < 0.5) {
                positions[i].y -= 3;
            }
        });

        return positions;
    }

    function createNodeMesh(node, pos) {
        const size = Math.max(1.0, 3.0 - (node.dependencyCount || 0) * 0.05);
        const color = node.healthColor || '#00ff88';
        const type = node.type || 'model';
        let geometry;

        switch (TYPE_SHAPES[type] || 'sphere') {
            case 'box':
                geometry = new THREE.BoxGeometry(size, size, size);
                break;
            case 'cone':
                geometry = new THREE.ConeGeometry(size * 0.7, size * 1.4, 6);
                break;
            case 'sphere':
                geometry = new THREE.SphereGeometry(size * 0.6, 16, 16);
                break;
            case 'diamond':
                geometry = new THREE.OctahedronGeometry(size * 0.7);
                break;
            case 'hexagon':
                geometry = new THREE.CylinderGeometry(size * 0.7, size * 0.7, size * 0.5, 6);
                break;
            case 'octagon':
                geometry = new THREE.CylinderGeometry(size * 0.7, size * 0.7, size * 0.5, 8);
                break;
            case 'ring':
                geometry = new THREE.TorusGeometry(size * 0.6, size * 0.2, 8, 16);
                break;
            default:
                geometry = new THREE.SphereGeometry(size * 0.6, 16, 16);
        }

        const material = new THREE.MeshPhysicalMaterial({
            color: new THREE.Color(color),
            metalness: 0.3,
            roughness: 0.2,
            clearcoat: 0.1,
            emissive: new THREE.Color(color),
            emissiveIntensity: 0.15,
            transparent: true,
            opacity: 0.9,
        });

        const mesh = new THREE.Mesh(geometry, material);
        mesh.position.set(pos.x, pos.y, pos.z);
        mesh.castShadow = true;

        const glowGeometry = new THREE.SphereGeometry(size * 1.2, 16, 16);
        const glowMaterial = new THREE.MeshBasicMaterial({
            color: new THREE.Color(color),
            transparent: true,
            opacity: 0.06,
            blending: THREE.AdditiveBlending,
        });
        const glow = new THREE.Mesh(glowGeometry, glowMaterial);
        mesh.add(glow);

        const pulseGeo = new THREE.RingGeometry(size * 0.5, size * 0.55, 32);
        const pulseMat = new THREE.MeshBasicMaterial({
            color: new THREE.Color(color),
            transparent: true,
            opacity: 0.3,
            side: THREE.DoubleSide,
            blending: THREE.AdditiveBlending,
        });
        const pulseRing = new THREE.Mesh(pulseGeo, pulseMat);
        pulseRing.rotation.x = Math.PI / 2;
        mesh.add(pulseRing);
        mesh.userData.pulseRing = pulseRing;
        mesh.userData.pulsePhase = Math.random() * Math.PI * 2;

        const label = createLabel(node.label, size);
        label.position.y = -size * 1.2;
        mesh.add(label);

        return mesh;
    }

    function createLabel(text, size) {
        const canvas = document.createElement('canvas');
        canvas.width = 256;
        canvas.height = 64;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = 'transparent';
        ctx.fillRect(0, 0, 256, 64);
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.font = '500 20px Inter, sans-serif';
        ctx.fillStyle = 'rgba(200,200,220,0.7)';
        ctx.fillText(text, 128, 32);

        const texture = new THREE.CanvasTexture(canvas);
        texture.needsUpdate = true;
        const material = new THREE.SpriteMaterial({
            map: texture,
            transparent: true,
            depthWrite: false,
        });
        const sprite = new THREE.Sprite(material);
        sprite.scale.set(size * 2, size * 0.5, 1);
        return sprite;
    }

    function createEdgeLine(from, to, edgeData) {
        const color = edgeData.color || '#888888';
        const points = [from.clone(), to.clone()];
        const mid = new THREE.Vector3().addVectors(from, to).multiplyScalar(0.5);
        const offset = new THREE.Vector3(
            (Math.random() - 0.5) * 2,
            (Math.random() - 0.5) * 2,
            (Math.random() - 0.5) * 2
        );
        mid.add(offset);

        const curve = new THREE.QuadraticBezierCurve3(from, mid, to);
        const curvePoints = curve.getPoints(20);
        const geometry = new THREE.BufferGeometry().setFromPoints(curvePoints);

        const material = new THREE.LineBasicMaterial({
            color: new THREE.Color(color),
            transparent: true,
            opacity: edgeData.type === 'test' ? 0.2 : 0.35,
            linewidth: 1,
        });

        const line = new THREE.Line(geometry, material);
        line.userData.edgeData = edgeData;

        if (edgeData.type === 'test') {
            line.material.opacity = 0.15;
        }

        return line;
    }

    function updateStats(data) {
        const nodes = data.nodes || [];
        const edges = data.edges || [];
        const health = data.averageHealth || 0;

        document.getElementById('stat-nodes').textContent = nodes.length;
        document.getElementById('stat-edges').textContent = edges.length;
        document.getElementById('stat-health').textContent = (health * 100).toFixed(0) + '%';

        const testEdges = edges.filter(e => e.type === 'test');
        document.getElementById('stat-tests').textContent = testEdges.length;
    }

    function setupRaycaster() {
        const raycaster = new THREE.Raycaster();
        const mouse = new THREE.Vector2();
        let mouseMoved = false;

        renderer.domElement.addEventListener('mousedown', () => { mouseMoved = false; });
        renderer.domElement.addEventListener('mousemove', (e) => {
            mouseMoved = true;
            mouse.x = (e.clientX / window.innerWidth) * 2 - 1;
            mouse.y = -(e.clientY / window.innerHeight) * 2 + 1;

            raycaster.setFromCamera(mouse, camera);
            const meshes = Object.values(nodeMeshMap || {});
            const intersects = raycaster.intersectObjects(meshes);

            if (intersects.length > 0) {
                const mesh = intersects[0].object;
                if (hoveredNode !== mesh) {
                    if (hoveredNode) {
                        resetNodeHighlight(hoveredNode);
                    }
                    hoveredNode = mesh;
                    highlightNode(mesh);
                    renderer.domElement.style.cursor = 'pointer';
                }
            } else {
                if (hoveredNode) {
                    resetNodeHighlight(hoveredNode);
                    hoveredNode = null;
                    renderer.domElement.style.cursor = 'default';
                }
            }
        });

        renderer.domElement.addEventListener('click', (e) => {
            if (mouseMoved) return;
            mouse.x = (e.clientX / window.innerWidth) * 2 - 1;
            mouse.y = -(e.clientY / window.innerHeight) * 2 + 1;

            raycaster.setFromCamera(mouse, camera);
            const meshes = Object.values(nodeMeshMap || {});
            const intersects = raycaster.intersectObjects(meshes);

            if (intersects.length > 0) {
                const mesh = intersects[0].object;
                const nodeId = mesh.userData.nodeId;
                if (nodeId) {
                    controls.autoRotate = false;
                    openNodePanel(nodeId);
                }
            } else {
                if (!e.target.closest('#side-panel') && !e.target.closest('#search-results')) {
                    closeNodePanel();
                }
            }
        });
    }

    function highlightNode(mesh) {
        if (!mesh) return;
        mesh.material.emissiveIntensity = 0.4;
        const scale = mesh.scale.x;
        mesh.scale.set(1.3, 1.3, 1.3);
    }

    function resetNodeHighlight(mesh) {
        if (!mesh) return;
        mesh.material.emissiveIntensity = 0.15;
        mesh.scale.set(1, 1, 1);
    }

    function openNodePanel(nodeId) {
        selectedNodeId = nodeId;
        const panel = document.getElementById('side-panel');
        const body = document.getElementById('panel-body');
        panel.classList.add('open');
        body.innerHTML = '<div class="panel-loading">Loading component details...</div>';

        fetch(API + '/graph/node/' + encodeURIComponent(nodeId))
            .then(r => r.json())
            .then(data => {
                renderNodePanel(data);
            })
            .catch(() => {
                body.innerHTML = '<div class="panel-loading">Failed to load details.</div>';
            });

        highlightConnected(nodeId);
    }

    function closeNodePanel() {
        document.getElementById('side-panel').classList.remove('open');
        if (selectedNodeId) {
            unhighlightConnected(selectedNodeId);
            selectedNodeId = null;
        }
        controls.autoRotate = true;
    }

    function highlightConnected(nodeId) {
        if (!graphData || !nodeMeshMap) return;

        Object.values(nodeMeshMap).forEach(mesh => {
            mesh.material.opacity = 0.15;
        });

        const node = graphData.nodes.find(n => n.id === nodeId);
        if (node) {
            const connected = [nodeId, ...node.dependencies, ...node.dependents];
            meshLoop: for (const [id, mesh] of Object.entries(nodeMeshMap)) {
                if (connected.includes(id)) {
                    mesh.material.opacity = 1.0;
                }
            }
        }

        if (edgeLines) {
            edgeLines.children.forEach(line => {
                const ed = line.userData.edgeData;
                if (ed && (ed.sourceId === nodeId || ed.targetId === nodeId)) {
                    line.material.opacity = 0.8;
                } else {
                    line.material.opacity = 0.05;
                }
            });
        }
    }

    function unhighlightConnected(nodeId) {
        if (!nodeMeshMap) return;
        Object.values(nodeMeshMap).forEach(mesh => {
            mesh.material.opacity = 0.9;
        });
        if (edgeLines) {
            edgeLines.children.forEach(line => {
                const ed = line.userData.edgeData;
                line.material.opacity = (ed && ed.type === 'test') ? 0.15 : 0.35;
            });
        }
    }

    function renderNodePanel(data) {
        const node = data.node;
        if (!node) return;
        const body = document.getElementById('panel-body');
        document.getElementById('panel-title').textContent = node.label;

        let html = '';

        html += '<div class="node-info-grid">';
        html += `<div class="node-info-item"><div class="value">${node.type}</div><div class="label">Type</div></div>`;
        html += `<div class="node-info-item"><div class="value">${(node.healthScore * 100).toFixed(0)}%</div><div class="label">Health</div></div>`;
        html += `<div class="node-info-item"><div class="value">${node.complexityScore}</div><div class="label">Complexity</div></div>`;
        html += `<div class="node-info-item"><div class="value">${node.testCount}</div><div class="label">Tests</div></div>`;
        html += `<div class="node-info-item"><div class="value">${node.dependencyCount}</div><div class="label">Dependencies</div></div>`;
        html += `<div class="node-info-item"><div class="value">${node.dependentCount}</div><div class="label">Dependents</div></div>`;
        html += '</div>';

        html += '<div class="panel-section"><h4>Namespace</h4><code style="font-size:12px;color:#888;">' + node.namespace + '</code></div>';

        if (node.metadata && Object.keys(node.metadata).length > 0) {
            html += '<div class="panel-section"><h4>Metadata</h4><div style="font-size:12px;color:#888;">';
            for (const [key, val] of Object.entries(node.metadata)) {
                const str = typeof val === 'object' ? JSON.stringify(val) : String(val);
                html += `<div style="margin-bottom:4px;"><strong style="color:#aaa;">${key}:</strong> ${str.length > 100 ? str.slice(0, 100) + '...' : str}</div>`;
            }
            html += '</div></div>';
        }

        if (data.dependencies && data.dependencies.incoming.length > 0) {
            html += '<div class="panel-section"><h4>Incoming Dependencies (' + data.dependencies.incoming.length + ')</h4>';
            html += '<div class="edge-list">';
            data.dependencies.incoming.forEach(d => {
                html += `<div class="edge-item"><div class="edge-color" style="background:#ff8800"></div><span class="edge-label">${d.label}</span></div>`;
            });
            html += '</div></div>';
        }

        if (data.dependencies && data.dependencies.outgoing.length > 0) {
            html += '<div class="panel-section"><h4>Outgoing Dependencies (' + data.dependencies.outgoing.length + ')</h4>';
            html += '<div class="edge-list">';
            data.dependencies.outgoing.forEach(d => {
                html += `<div class="edge-item"><div class="edge-color" style="background:#00ccff"></div><span class="edge-label">${d.label}</span></div>`;
            });
            html += '</div></div>';
        }

        if (data.edges && data.edges.length > 0) {
            const tests = data.edges.filter(e => e.type === 'test');
            if (tests.length > 0) {
                html += '<div class="panel-section"><h4>Tests (' + tests.length + ')</h4>';
                tests.forEach(t => {
                    html += `<div class="test-result passed">✓ ${t.label || 'Test'}</div>`;
                });
                html += '</div>';
            }

            const routes = data.edges.filter(e => e.type === 'route');
            if (routes.length > 0) {
                html += '<div class="panel-section"><h4>Routes (' + routes.length + ')</h4>';
                routes.forEach(r => {
                    html += `<div class="edge-item"><div class="edge-color" style="background:#66ddaa"></div><span class="edge-label">${r.label || r.id}</span></div>`;
                });
                html += '</div>';
            }
        }

        if (data.sourceCode) {
            html += '<div class="panel-section"><h4>Source Code</h4><div class="source-code">' + escapeHtml(data.sourceCode) + '</div></div>';
        }

        body.innerHTML = html;
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text.slice(0, 8000);
        return div.innerHTML;
    }

    function connectWebSocket() {
        try {
            ws = new WebSocket('ws://' + WS_HOST + ':' + WS_PORT);
            const status = document.getElementById('connection-status');

            ws.onopen = () => {
                status.querySelector('.status-dot').classList.add('connected');
                status.querySelector('.status-text').textContent = 'Connected';
                ws.send(JSON.stringify({ type: 'subscribe_tests' }));
            };

            ws.onclose = () => {
                status.querySelector('.status-dot').classList.remove('connected');
                status.querySelector('.status-text').textContent = 'Disconnected';
                setTimeout(connectWebSocket, 3000);
            };

            ws.onmessage = (event) => {
                try {
                    const msg = JSON.parse(event.data);
                    handleWsMessage(msg);
                } catch (e) { /* ignore parse errors */ }
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
                msg.results.forEach(r => handleTestUpdate(r));
                break;
            case 'graph_update':
                if (msg.graph) {
                    rebuildGraph(msg.graph);
                }
                break;
            case 'pong':
                break;
        }
    }

    function handleTestUpdate(msg) {
        const passed = msg.passed;
        const componentId = msg.componentId;
        const testName = msg.testName || 'Test';

        showTestNotification(testName, passed);

        if (componentId && nodeMeshMap[componentId]) {
            const mesh = nodeMeshMap[componentId];
            animateTestPulse(mesh, passed);
        }

        if (!componentId && graphData) {
            graphData.nodes.forEach(node => {
                if (nodeMeshMap[node.id]) {
                    animateTestPulse(nodeMeshMap[node.id], passed);
                }
            });
        }

        if (passed && componentId && graphData) {
            const node = graphData.nodes.find(n => n.id === componentId);
            if (node) {
                const newHealth = Math.min(1, (node.healthScore || 0.5) + 0.05);
                node.healthScore = newHealth;
                node.healthColor = newHealth >= 0.8 ? '#00ff88' : newHealth >= 0.5 ? '#ffaa00' : '#ff3355';
                if (nodeMeshMap[componentId]) {
                    nodeMeshMap[componentId].material.color.set(node.healthColor);
                    nodeMeshMap[componentId].material.emissive.set(node.healthColor);
                }
            }
        }
    }

    function animateTestPulse(mesh, passed) {
        const color = passed ? '#00ff88' : '#ff3355';
        const originalColor = mesh.material.color.clone();
        const originalEmissive = mesh.material.emissive.clone();
        const originalIntensity = mesh.material.emissiveIntensity;

        mesh.material.color.set(color);
        mesh.material.emissive.set(color);
        mesh.material.emissiveIntensity = 1.0;

        const duration = passed ? 1000 : 1500;
        const start = performance.now();

        function pulseAnim() {
            const elapsed = performance.now() - start;
            const t = Math.min(1, elapsed / duration);

            if (!passed) {
                const scale = 1 + Math.sin(t * Math.PI * 4) * 0.2 * (1 - t);
                mesh.scale.set(scale, scale, scale);
            }

            if (t < 1) {
                requestAnimationFrame(pulseAnim);
            } else {
                mesh.material.color.copy(originalColor);
                mesh.material.emissive.copy(originalEmissive);
                mesh.material.emissiveIntensity = originalIntensity;
                mesh.scale.set(1, 1, 1);
            }
        }

        pulseAnim();
    }

    function showTestNotification(testName, passed) {
        const container = document.getElementById('test-notification-container');
        const notif = document.createElement('div');
        notif.className = 'test-notification ' + (passed ? 'passed' : 'failed');
        notif.innerHTML = (passed ? '✓' : '✗') + ' ' + (testName.length > 50 ? testName.slice(0, 50) + '...' : testName);
        container.appendChild(notif);

        setTimeout(() => {
            notif.style.animation = 'fadeOut 0.3s ease forwards';
            setTimeout(() => notif.remove(), 300);
        }, 3000);

        if (container.children.length > 5) {
            container.firstChild.remove();
        }
    }

    function rebuildGraph(graph) {
        if (edgeLines) { scene.remove(edgeLines); edgeLines = null; }
        const nodes = Object.values(nodeMeshMap || {});
        nodes.forEach(m => {
            if (m.parent) m.parent.remove(m);
        });
        nodeMeshMap = {};

        buildGraph(graph);
        updateStats(graph);
    }

    function setupUI() {
        document.getElementById('panel-close').addEventListener('click', closeNodePanel);

        document.querySelectorAll('[data-filter]').forEach(btn => {
            btn.addEventListener('click', function () {
                const type = this.dataset.filter;
                document.querySelectorAll('[data-filter]').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                filterNodes(type);
            });
        });

        document.getElementById('btn-heatmap').addEventListener('click', toggleHeatmap);
        document.getElementById('btn-snapshot').addEventListener('click', takeSnapshot);
        document.getElementById('btn-export').addEventListener('click', exportGraph);
        document.getElementById('btn-dashboard').addEventListener('click', () => {
            window.location.href = '/canvas/dashboard';
        });

        const searchInput = document.getElementById('search-input');
        const searchResults = document.getElementById('search-results');
        let searchTimeout;

        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            const q = this.value.trim();

            if (q.length < 1) {
                searchResults.classList.add('hidden');
                return;
            }

            searchTimeout = setTimeout(() => {
                fetch(API + '/search?q=' + encodeURIComponent(q))
                    .then(r => r.json())
                    .then(data => {
                        if (data.results && data.results.length > 0) {
                            searchResults.innerHTML = data.results.map(r => {
                                const color = TYPE_COLORS[r.type] || '#888';
                                return `<div class="search-result-item" data-id="${r.id}">
                                    <span class="type-badge" style="background:${color}22;color:${color}">${r.type}</span>
                                    <span class="name">${r.label}</span>
                                    <span style="margin-left:auto;font-size:11px;color:#555;">${(r.healthScore*100).toFixed(0)}%</span>
                                </div>`;
                            }).join('');
                            searchResults.classList.remove('hidden');

                            searchResults.querySelectorAll('.search-result-item').forEach(el => {
                                el.addEventListener('click', () => {
                                    const id = el.dataset.id;
                                    if (id && nodeMeshMap[id]) {
                                        const pos = nodeMeshMap[id].position;
                                        camera.position.set(pos.x + 10, pos.y + 5, pos.z + 10);
                                        controls.target.copy(pos);
                                        controls.update();
                                        openNodePanel(id);
                                    }
                                    searchResults.classList.add('hidden');
                                    searchInput.value = '';
                                });
                            });
                        } else {
                            searchResults.innerHTML = '<div style="padding:16px;text-align:center;color:#555;">No results found</div>';
                            searchResults.classList.remove('hidden');
                        }
                    });
            }, 200);
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.search-box')) {
                searchResults.classList.add('hidden');
            }
        });
    }

    function filterNodes(type) {
        if (!graphData || !nodeMeshMap) return;

        if (type === 'all') {
            Object.values(nodeMeshMap).forEach(m => {
                m.visible = true;
            });
            if (edgeLines) {
                edgeLines.children.forEach(l => { l.visible = true; });
            }
            return;
        }

        const visibleIds = new Set();
        graphData.nodes.forEach(n => {
            if (n.type === type) visibleIds.add(n.id);
        });

        Object.entries(nodeMeshMap).forEach(([id, mesh]) => {
            mesh.visible = visibleIds.has(id);
        });

        if (edgeLines) {
            edgeLines.children.forEach(line => {
                const ed = line.userData.edgeData;
                line.visible = ed && visibleIds.has(ed.sourceId) && visibleIds.has(ed.targetId);
            });
        }
    }

    function toggleHeatmap() {
        heatmapMode = !heatmapMode;
        document.getElementById('btn-heatmap').classList.toggle('active');

        if (!graphData || !nodeMeshMap) return;

        if (heatmapMode) {
            fetch(API + '/heatmap')
                .then(r => r.json())
                .then(data => {
                    const heat = data.commitHeatmap || {};
                    Object.keys(nodeMeshMap).forEach(id => {
                        const mesh = nodeMeshMap[id];
                        const node = graphData.nodes.find(n => n.id === id);
                        if (node && mesh) {
                            const commits = heat[node.filePath] || 0;
                            const intensity = Math.min(1, commits / 10);
                            const c = new THREE.Color().setHSL(0.05 - intensity * 0.05, 0.9, 0.5);
                            mesh.material.color.set(c);
                            mesh.material.emissive.set(c);
                            mesh.material.emissiveIntensity = 0.2 + intensity * 0.4;
                        }
                    });
                });
        } else {
            graphData.nodes.forEach(node => {
                const mesh = nodeMeshMap[node.id];
                if (mesh) {
                    mesh.material.color.set(node.healthColor || '#00ff88');
                    mesh.material.emissive.set(node.healthColor || '#00ff88');
                    mesh.material.emissiveIntensity = 0.15;
                }
            });
        }
    }

    function takeSnapshot() {
        fetch(API + '/snapshot', { method: 'POST' })
            .then(r => r.json())
            .then(data => {
                showTestNotification('Snapshot taken: ' + (data.snapshotId || '').slice(0, 8) + '...', true);
            })
            .catch(() => {
                showTestNotification('Snapshot failed', false);
            });
    }

    function exportGraph() {
        fetch(API + '/export')
            .then(r => r.json())
            .then(data => {
                const json = JSON.stringify(data, null, 2);
                const blob = new Blob([json], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'canvas-architecture-' + new Date().toISOString().slice(0, 10) + '.json';
                a.click();
                URL.revokeObjectURL(url);
                showTestNotification('Architecture exported', true);
            });
    }

    function onResize() {
        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(window.innerWidth, window.innerHeight);
    }

    function animate() {
        requestAnimationFrame(animate);
        const elapsed = clock.getElapsedTime();

        if (particles) {
            particles.rotation.y = elapsed * 0.005;
            particles.rotation.x = Math.sin(elapsed * 0.002) * 0.05;
        }

        if (nodeMeshMap) {
            Object.values(nodeMeshMap).forEach(mesh => {
                mesh.rotation.x = Math.sin(elapsed * 0.3 + mesh.userData.pulsePhase) * 0.1;
                mesh.rotation.y += 0.005 * ANIMATION_SPEED;

                if (mesh.userData.pulseRing) {
                    const ring = mesh.userData.pulseRing;
                    const phase = mesh.userData.pulsePhase || 0;
                    const scale = 1 + Math.sin(elapsed * 0.5 + phase) * 0.3;
                    ring.scale.set(scale, scale, scale);
                    ring.material.opacity = 0.2 + Math.sin(elapsed * 0.5 + phase) * 0.15;
                }

                if (!selectedNodeId) {
                    const health = mesh.userData.nodeData?.healthScore || 0.5;
                    const pulseIntensity = 0.1 + (1 - health) * 0.15;
                    mesh.material.emissiveIntensity = 0.1 + Math.sin(elapsed * 0.8 + (mesh.userData.pulsePhase || 0)) * pulseIntensity * 0.5;
                }
            });
        }

        if (edgeLines) {
            edgeLines.children.forEach((line, i) => {
                if (line.material.opacity < 0.4 && !selectedNodeId) {
                    const baseOpacity = line.userData.edgeData?.type === 'test' ? 0.15 : 0.35;
                    line.material.opacity = baseOpacity + Math.sin(elapsed * 0.3 + i) * 0.05;
                }
            });
        }

        controls.update();

        renderer.render(scene, camera);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    animate();
})();
