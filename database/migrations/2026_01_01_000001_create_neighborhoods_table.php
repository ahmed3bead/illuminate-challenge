<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('neighborhoods', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('name')->unique();
            $table->text('boundary');
            $table->json('properties')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('neighborhoods');
    }
};
