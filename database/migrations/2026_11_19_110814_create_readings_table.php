<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transformer_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('msg_id')->nullable();
            $table->float('temp');
            $table->float('hum');
            $table->boolean('alarm_active')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['transformer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('readings');
    }
};