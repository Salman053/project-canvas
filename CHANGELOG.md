# Changelog

## v1.0.0 — 2026-07-23

Initial release of Laravel Canvas.

### Added

- 3D architecture visualization powered by THREE.js with orbital controls
- Automatic codebase scanning for Models, Controllers, Jobs, Listeners, Policies, Middleware, Providers, and Routes
- Dependency mapping between components (constructor injections, use statements, relationships)
- Health scoring with color-coded indicators (green/yellow/red)
- Real-time test mode — graph animates with green/red pulses as tests run
- Search and filter by component type with autocomplete
- Git commit heatmap visualization
- Architecture snapshots — capture and compare states over time
- JSON export for sharing architecture data
- REST API at `/api/canvas/` for programmatic access
- Dashboard with aggregate statistics at `/canvas/dashboard`
- Artisan commands: `canvas:scan` and `canvas:serve`
- WebSocket server for live updates
- Customizable configuration via published config file
