<?php

declare(strict_types=1);

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 20)->default(UserRole::VIEWER->value)->index();
            $table->char('api_token_hash', 64)->nullable()->unique();
            $table->timestamp('api_token_last_used_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['api_token_hash']);
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'api_token_hash', 'api_token_last_used_at']);
        });
    }
};
