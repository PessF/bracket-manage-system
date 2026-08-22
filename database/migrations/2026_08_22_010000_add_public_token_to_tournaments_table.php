<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_tournaments', function (Blueprint $table): void {
            $table->uuid('public_token')->nullable()->unique('uq_external_tournaments_public_token');
        });

        DB::table('external_tournaments')->select('id')->orderBy('id')->eachById(function (object $tournament): void {
            DB::table('external_tournaments')->where('id', $tournament->id)->update([
                'public_token' => (string) Str::uuid(),
            ]);
        }, 100, 'id');
    }

    public function down(): void
    {
        Schema::table('external_tournaments', function (Blueprint $table): void {
            $table->dropUnique('uq_external_tournaments_public_token');
            $table->dropColumn('public_token');
        });
    }
};
