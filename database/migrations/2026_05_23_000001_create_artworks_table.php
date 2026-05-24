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
        Schema::create('artworks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('inventory_code')->unique();
            $table->string('sku')->nullable()->unique();

            $table->string('title');
            $table->text('description')->nullable();

            $table->date('started_date')->nullable();
            $table->boolean('started_date_is_estimated')->default(false);

            $table->date('finished_date')->nullable();
            $table->boolean('finished_date_is_estimated')->default(false);

            $table->string('medium')->nullable();
            $table->string('surface')->nullable();

            $table->decimal('width', 10, 2)->nullable();
            $table->decimal('height', 10, 2)->nullable();
            $table->decimal('depth', 10, 2)->nullable();
            $table->string('dimension_unit')->default('in');

            $table->string('category')->nullable();
            $table->string('style')->nullable();
            $table->string('subject')->nullable();

            $table->string('status')->default('in_inventory');
            $table->string('condition')->default('good');

            $table->string('location')->nullable();
            $table->string('storage_area')->nullable();

            $table->decimal('estimated_value', 10, 2)->nullable();
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->string('currency')->default('USD');

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('artworks');
    }
};
