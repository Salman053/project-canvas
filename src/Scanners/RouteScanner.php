<?php

declare(strict_types=1);

namespace Salman053\Canvas\Scanners;

use Closure;
use Illuminate\Support\Facades\Route;
use Salman053\Canvas\Data\Edge;
use Salman053\Canvas\Data\Node;
use Throwable;

class RouteScanner
{
    public function scan(): array
    {
        $routeNodes = [];
        $routeEdges = [];

        try {
            $routes = Route::getRoutes();
        } catch (Throwable) {
            return ['nodes' => [], 'edges' => []];
        }

        foreach ($routes as $route) {
            $action = $route->getAction();
            $controller = $action['controller'] ?? $action['uses'] ?? null;

            if ($controller instanceof Closure) {
                continue;
            }

            if (! is_string($controller) || $controller === 'Closure') {
                continue;
            }

            $routeId = 'route_'.str_replace(['\\', '@', '/'], '_', $route->uri().'_'.$controller);

            $routeNode = new Node(
                id: $routeId,
                label: $route->uri(),
                type: Node::TYPE_ROUTE,
                namespace: $controller,
                filePath: '',
            );

            $methods = $route->methods();
            $routeNode->setMetadata('methods', array_values(array_diff($methods, ['HEAD'])));
            $routeNode->setMetadata('name', $route->getName());
            $routeNode->setMetadata('middleware', $route->middleware());
            $routeNode->setMetadata('controller', $controller);

            $routeNodes[$routeId] = $routeNode;

            $parts = explode('@', $controller);
            $controllerClass = $parts[0];
            $controllerNodeId = 'controller_'.str_replace('\\', '_', $controllerClass);

            $edge = new Edge(
                id: 'edge_route_'.str_replace(['\\', '@', '/'], '_', $route->uri().'_'.$controllerClass),
                sourceId: $routeId,
                targetId: $controllerNodeId,
                type: Edge::TYPE_ROUTE,
                label: $route->getName() ?: implode('|', $route->methods()).' '.$route->uri(),
            );

            $routeEdges[$edge->getId()] = $edge;
        }

        return ['nodes' => $routeNodes, 'edges' => $routeEdges];
    }
}
