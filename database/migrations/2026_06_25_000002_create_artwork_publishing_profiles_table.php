<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('artwork_publishing_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artwork_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('short_description')->nullable();
            $table->text('product_description')->nullable();
            $table->text('story_inspiration')->nullable();
            $table->text('materials_process')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('artwork_publishing_profiles');
    }
};
