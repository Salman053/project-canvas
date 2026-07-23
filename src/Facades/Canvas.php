<?php

declare(strict_types=1);

namespace Salman053\Canvas\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Salman053\Canvas\Data\ArchitectureGraph scan()
 * @method static \Salman053\Canvas\Data\ArchitectureGraph|null getGraph()
 * @method static array getDashboardStats()
 * @method static string takeSnapshot(string $label = '')
 * @method static array export()
 *
 * @see \Salman053\Canvas\Canvas
 */
class Canvas extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Salman053\Canvas\Canvas::class;
    }
}
