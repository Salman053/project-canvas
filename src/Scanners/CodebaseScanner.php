<?php

declare(strict_types=1);

namespace Salman053\Canvas\Scanners;

use Salman053\Canvas\Data\ArchitectureGraph;
use Salman053\Canvas\Data\Edge;
use Salman053\Canvas\Data\Node;

class CodebaseScanner
{
    private ArchitectureGraph $graph;

    private ModelScanner $modelScanner;

    private ControllerScanner $controllerScanner;

    private JobScanner $jobScanner;

    private ListenerScanner $listenerScanner;

    private PolicyScanner $policyScanner;

    private MiddlewareScanner $middlewareScanner;

    private ProviderScanner $providerScanner;

    private RouteScanner $routeScanner;

    private DependencyScanner $dependencyScanner;

    private TestScanner $testScanner;

    private GitAnalyzer $gitAnalyzer;

    public function __construct()
    {
        $this->graph = new ArchitectureGraph;
        $this->modelScanner = new ModelScanner;
        $this->controllerScanner = new ControllerScanner;
        $this->jobScanner = new JobScanner;
        $this->listenerScanner = new ListenerScanner;
        $this->policyScanner = new PolicyScanner;
        $this->middlewareScanner = new MiddlewareScanner;
        $this->providerScanner = new ProviderScanner;
        $this->routeScanner = new RouteScanner;
        $this->dependencyScanner = new DependencyScanner;
        $this->testScanner = new TestScanner;
        $this->gitAnalyzer = new GitAnalyzer;
    }

    public function scan(): ArchitectureGraph
    {
        $models = $this->modelScanner->scan();
        $controllers = $this->controllerScanner->scan();
        $jobs = $this->jobScanner->scan();
        $listeners = $this->listenerScanner->scan();
        $policies = $this->policyScanner->scan();
        $middleware = $this->middlewareScanner->scan();
        $providers = $this->providerScanner->scan();

        $allNodes = array_merge(
            $models,
            $controllers,
            $jobs,
            $listeners,
            $policies,
            $middleware,
            $providers,
        );

        foreach ($allNodes as $node) {
            $this->graph->addNode($node);
        }

        $this->dependencyScanner->setAllNodes($allNodes);
        $dependencyEdges = $this->dependencyScanner->scan();
        foreach ($dependencyEdges as $edge) {
            $this->graph->addEdge($edge);
        }

        $this->testScanner->setAllNodes($allNodes);
        $testEdges = $this->testScanner->scan();
        foreach ($testEdges as $edge) {
            $this->graph->addEdge($edge);
        }

        $routeData = $this->routeScanner->scan();
        foreach ($routeData['nodes'] as $node) {
            $this->graph->addNode($node);
        }
        foreach ($routeData['edges'] as $edge) {
            $this->graph->addEdge($edge);
        }

        foreach ($allNodes as $nodeA) {
            foreach ($allNodes as $nodeB) {
                if ($nodeA->getId() === $nodeB->getId()) {
                    continue;
                }

                if ($nodeA->getType() === Node::TYPE_MODEL) {
                    $relationships = $nodeA->getMetadata('relationships', []);
                    foreach ($relationships as $rel) {
                        $relClass = $rel['method'] ?? '';

                        if ($relClass && class_exists($relClass)) {
                            $targetId = 'model_'.str_replace('\\', '_', $relClass);

                            if ($this->graph->getNode($targetId)) {
                                $edge = new Edge(
                                    id: 'edge_rel_'.$nodeA->getId().'_'.$targetId,
                                    sourceId: $nodeA->getId(),
                                    targetId: $targetId,
                                    type: Edge::TYPE_RELATIONSHIP,
                                    label: $rel['type'] ?? 'relationship',
                                );
                                $this->graph->addEdge($edge);
                            }
                        }
                    }
                }
            }
        }

        return $this->graph;
    }

    public function getGitAnalyzer(): GitAnalyzer
    {
        return $this->gitAnalyzer;
    }
}
