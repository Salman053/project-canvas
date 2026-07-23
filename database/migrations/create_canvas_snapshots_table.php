<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canvas_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('label')->nullable();
            $table->json('graph_data');
            $table->json('dashboard_stats');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canvas_snapshots');
    }
};
