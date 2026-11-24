<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * token_hash was created as char(64) for SHA-256 reset links, but reset
     * codes are stored as bcrypt hashes (60 characters). Postgres blank-pads
     * char columns, so the hash read back no longer matched the code and every
     * valid code was rejected as incorrect.
     */
    public function up(): void
    {
        Schema::table('password_resets', function (Blueprint $table) {
            $table->string('token_hash', 255)->change();
        });
    }

    public function down(): void
    {
        Schema::table('password_resets', function (Blueprint $table) {
            $table->char('token_hash', 64)->change();
        });
    }
};
