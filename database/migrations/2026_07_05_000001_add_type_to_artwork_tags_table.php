<?php

use App\Support\ArtworkTagType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artwork_tags', function (Blueprint $table) {
            $table->string('type', 20)->default(ArtworkTagType::GENERAL)->after('normalized_name');
        });

        DB::table('artwork_tags')->update(['type' => ArtworkTagType::GENERAL]);

        Schema::table('artwork_tags', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'normalized_name']);
            $table->unique(['user_id', 'type', 'normalized_name']);
        });
    }

    public function down(): void
    {
        Schema::table('artwork_tags', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'type', 'normalized_name']);
            $table->unique(['user_id', 'normalized_name']);
            $table->dropColumn('type');
        });
    }
};
