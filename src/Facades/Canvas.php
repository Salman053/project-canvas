<?php

declare(strict_types=1);

namespace VendorName\Canvas\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \VendorName\Canvas\Data\ArchitectureGraph scan()
 * @method static \VendorName\Canvas\Data\ArchitectureGraph|null getGraph()
 * @method static array getDashboardStats()
 * @method static string takeSnapshot(string $label = '')
 * @method static array export()
 *
 * @see \VendorName\Canvas\Canvas
 */
class Canvas extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \VendorName\Canvas\Canvas::class;
    }
}
