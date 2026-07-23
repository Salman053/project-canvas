<?php

declare(strict_types=1);

namespace VendorName\Canvas\Data;

class Node
{
    public const TYPE_MODEL = 'model';
    public const TYPE_CONTROLLER = 'controller';
    public const TYPE_JOB = 'job';
    public const TYPE_LISTENER = 'listener';
    public const TYPE_POLICY = 'policy';
    public const TYPE_MIDDLEWARE = 'middleware';
    public const TYPE_PROVIDER = 'provider';
    public const TYPE_EVENT = 'event';
    public const TYPE_ROUTE = 'route';

    private array $metadata = [];

    private array $testResults = [];

    private float $healthScore = 1.0;

    private int $complexityScore = 0;

    private array $dependencies = [];

    private array $dependents = [];

    public function __construct(
        private readonly string $id,
        private readonly string $label,
        private readonly string $type,
        private readonly string $namespace,
        private readonly string $filePath,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function setMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function getAllMetadata(): array
    {
        return $this->metadata;
    }

    public function addTestResult(string $testName, bool $passed, float $duration = 0.0): void
    {
        $this->testResults[] = [
            'name' => $testName,
            'passed' => $passed,
            'duration' => $duration,
            'timestamp' => now()->toIso8601String(),
        ];

        $this->recalculateHealth();
    }

    public function getTestResults(): array
    {
        return $this->testResults;
    }

    public function getTestPassRate(): float
    {
        if (empty($this->testResults)) {
            return 1.0;
        }

        $passed = count(array_filter($this->testResults, fn ($r) => $r['passed']));

        return $passed / count($this->testResults);
    }

    public function setHealthScore(float $score): void
    {
        $this->healthScore = max(0.0, min(1.0, $score));
    }

    public function getHealthScore(): float
    {
        return $this->healthScore;
    }

    public function getHealthColor(): string
    {
        return match (true) {
            $this->healthScore >= 0.8 => '#00ff88',
            $this->healthScore >= 0.5 => '#ffaa00',
            default => '#ff3355',
        };
    }

    public function setComplexityScore(int $score): void
    {
        $this->complexityScore = $score;
    }

    public function getComplexityScore(): int
    {
        return $this->complexityScore;
    }

    public function addDependency(string $nodeId): void
    {
        if (! in_array($nodeId, $this->dependencies)) {
            $this->dependencies[] = $nodeId;
        }
    }

    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function addDependent(string $nodeId): void
    {
        if (! in_array($nodeId, $this->dependents)) {
            $this->dependents[] = $nodeId;
        }
    }

    public function getDependents(): array
    {
        return $this->dependents;
    }

    public function getSourceCode(): ?string
    {
        if (! file_exists($this->filePath)) {
            return null;
        }

        return file_get_contents($this->filePath) ?: null;
    }

    private function recalculateHealth(): void
    {
        $passRate = $this->getTestPassRate();
        $depFactor = max(0, 1 - (count($this->dependencies) * 0.05));
        $complexityFactor = max(0, 1 - ($this->complexityScore * 0.02));

        $this->healthScore = ($passRate * 0.5) + ($depFactor * 0.25) + ($complexityFactor * 0.25);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'type' => $this->type,
            'namespace' => $this->namespace,
            'filePath' => $this->filePath,
            'metadata' => $this->metadata,
            'healthScore' => $this->healthScore,
            'healthColor' => $this->getHealthColor(),
            'complexityScore' => $this->complexityScore,
            'testPassRate' => $this->getTestPassRate(),
            'testCount' => count($this->testResults),
            'dependencyCount' => count($this->dependencies),
            'dependentCount' => count($this->dependents),
        ];
    }
}
