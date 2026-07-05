<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('artworks', 'artwork_type')) {
            Schema::table('artworks', function (Blueprint $table): void {
                $table->dropColumn('artwork_type');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('artworks', 'artwork_type')) {
            Schema::table('artworks', function (Blueprint $table): void {
                $table->string('artwork_type')->nullable()->after('completed_date');
            });
        }
    }
};
