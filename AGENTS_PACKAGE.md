# Laravel Canvas — AI Guidance

Laravel Canvas is an immersive 3D visualization tool for Laravel applications. It scans your codebase, maps architecture components, and renders them as an interactive 3D graph with real-time test updates.

## Key Concepts

- **Codebase Scanner**: Scans app/Models, app/Http/Controllers, app/Jobs, app/Listeners, app/Policies, app/Http/Middleware, and app/Providers to build a complete architecture graph.
- **Architecture Graph**: A graph data structure with typed Nodes (models, controllers, etc.) and typed Edges (relationships, dependencies, events, routes, tests).
- **Health Scoring**: Each component gets a health score (0-1) based on test pass rate, dependency count, and cyclomatic complexity.
- **WebSocket Server**: Real-time communication for live test result visualization.
- **3D Visualization**: THREE.js-powered interactive graph with color-coded nodes, connection beams, and orbital controls.

## Commands

- `php artisan canvas:scan` — Scan codebase and display architecture stats
- `php artisan canvas:serve` — Start the visualization + WebSocket server

## API Endpoints

All under `/api/canvas/`:
- `GET /graph` — Full architecture graph
- `GET /graph/node/{id}` — Node details with source code and dependencies
- `GET /search?q=` — Search nodes by name/namespace
- `GET /dashboard` — Aggregate statistics
- `GET /health` — Component health distribution and god classes
- `GET /filter/{type}` — Filter nodes by component type
- `GET /heatmap` — Git commit heatmap data
- `POST /snapshot` — Take a graph snapshot
- `GET /export` — Export full architecture as JSON

## Frontend

- THREE.js 3D graph at `/canvas`
- Dashboard at `/canvas/dashboard`

## Architecture Rules

- Add scanner classes in `src/Scanners/` for new component types.
- Add new edge types in `src/Data/Edge.php` TYPE_ constants.
- Register new routes in `routes/canvas.php` under the api prefix.
- Keep the WebSocket protocol simple: JSON with `type` field.
- The visualization auto-connects to the WebSocket on page load.
