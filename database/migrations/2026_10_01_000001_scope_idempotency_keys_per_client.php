<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An Idempotency-Key belongs to the client that sent it, and a replay must
     * be the same request: keys are now unique per user and carry a hash of
     * the request they answered.
     */
    public function up(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table): void {
            $table->dropUnique(['key']);
            $table->unsignedBigInteger('user_id')->nullable()->after('id');
            $table->string('request_hash', 64)->nullable()->after('path');
            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'key']);
            $table->dropColumn(['user_id', 'request_hash']);
            $table->unique('key');
        });
    }
};
