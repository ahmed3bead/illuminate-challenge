<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->float('lat');
            $table->float('lng');
            $table->json('metadata');
            $table->json('tags')->nullable();
            $table->timestamp('occurred_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
