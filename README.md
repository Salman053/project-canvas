# Laravel Canvas

> An immersive 3D visualization tool that transforms your Laravel application's architecture into an interactive, living graph.

[![PHP](https://img.shields.io/badge/PHP-8.3-%23777BB4?logo=php)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-red?logo=laravel)](https://laravel.com)
[![Packagist](https://img.shields.io/packagist/v/salman053/project-canvas)](https://packagist.org/packages/salman053/project-canvas)
[![License](https://img.shields.io/github/license/Salman053/project-canvas)](https://github.com/Salman053/project-canvas/blob/main/LICENSE)

## Overview

Laravel Canvas scans your entire Laravel codebase, identifies all key architectural components (Models, Controllers, Jobs, Listeners, Policies, Middleware, Service Providers, and Routes), maps their interconnections, and renders everything as a stunning 3D universe powered by THREE.js.

## Features

- **3D Architecture Visualization** — Interactive WebGL-powered 3D graph with orbital controls
- **Auto-Scanning** — Automatically discovers models, controllers, jobs, and more
- **Dependency Mapping** — Visualizes constructor injections, use statements, and relationships
- **Health Indicators** — Color-coded nodes: green (healthy), yellow (moderate), red (needs refactoring)
- **Real-Time Test Mode** — Watch tests animate the graph with green/red pulses
- **Search & Filter** — Instant node lookup with autocomplete, filter by component type
- **Heat Map** — Git commit frequency visualized on the graph
- **Snapshots** — Capture and compare architecture states over time
- **Export** — Generate shareable static JSON snapshots 

## Installation

```bash
composer require salman053/project-canvas
php artisan vendor:publish --provider="Salman053\Canvas\CanvasServiceProvider"
```

## Quick Start

Scan your codebase:

```bash
php artisan canvas:scan
```

Launch the 3D visualization:

```bash
php artisan canvas:serve
```

Open your browser to `http://localhost:8080/canvas` and run your tests in another terminal to see the graph come alive.

## Commands

- `php artisan canvas:scan` — Scan the codebase and display architecture statistics
- `php artisan canvas:serve` — Start the 3D visualization and WebSocket server

## API

Canvas provides a REST API at `/api/canvas/` for programmatic access to architecture data.

## Dashboard

A dashboard with aggregate statistics is available at `/canvas/dashboard`.

## Configuration

Publish the config file to customize scanning paths, visualization settings, and health thresholds.

```bash
php artisan vendor:publish --tag=canvas-config
```

## License

MIT
