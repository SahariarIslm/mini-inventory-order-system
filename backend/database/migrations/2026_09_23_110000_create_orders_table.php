<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('idempotency_key', 64);
            $table->enum('status', ['confirmed', 'cancelled'])->default('confirmed');
            $table->decimal('total', 12, 2);
            $table->timestamps();

            // Scoped per user: a retried request is claimed atomically by this
            // index, and one user can never collide with (or read back) another
            // user's order by reusing their key.
            $table->unique(['user_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
