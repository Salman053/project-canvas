<?php

declare(strict_types=1);

namespace VendorName\Canvas\Data;

class Edge
{
    public const TYPE_RELATIONSHIP = 'relationship';

    public const TYPE_DEPENDENCY = 'dependency';

    public const TYPE_EVENT = 'event';

    public const TYPE_ROUTE = 'route';

    public const TYPE_TEST = 'test';

    private array $metadata = [];

    public function __construct(
        private readonly string $id,
        private readonly string $sourceId,
        private readonly string $targetId,
        private readonly string $type,
        private ?string $label = null,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getSourceId(): string
    {
        return $this->sourceId;
    }

    public function getTargetId(): string
    {
        return $this->targetId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function setMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function getColor(): string
    {
        return match ($this->type) {
            self::TYPE_RELATIONSHIP => '#00ccff',
            self::TYPE_DEPENDENCY => '#ff8800',
            self::TYPE_EVENT => '#aa66ff',
            self::TYPE_ROUTE => '#66ddaa',
            self::TYPE_TEST => '#ff66aa',
            default => '#888888',
        };
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sourceId' => $this->sourceId,
            'targetId' => $this->targetId,
            'type' => $this->type,
            'label' => $this->label,
            'color' => $this->getColor(),
            'metadata' => $this->metadata,
        ];
    }
}
