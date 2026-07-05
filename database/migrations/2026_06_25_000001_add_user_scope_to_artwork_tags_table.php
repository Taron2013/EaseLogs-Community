<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artwork_tags', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('normalized_name')->nullable()->after('name');
        });

        $defaultUserId = User::query()->orderBy('id')->value('id');

        if ($defaultUserId !== null) {
            foreach (DB::table('artwork_tags')->get(['id', 'name']) as $tag) {
                DB::table('artwork_tags')->where('id', $tag->id)->update([
                    'user_id' => $defaultUserId,
                    'normalized_name' => mb_strtolower(trim((string) $tag->name)),
                ]);
            }
        }

        Schema::table('artwork_tags', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->unique(['user_id', 'normalized_name']);
        });
    }

    public function down(): void
    {
        Schema::table('artwork_tags', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'normalized_name']);
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('normalized_name');
            $table->unique('name');
        });
    }
};
