<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transformers', function (Blueprint $table) {
            $table->id();
            $table->string('mac_address', 20)->unique();
            $table->string('name')->nullable();
            $table->string('neighborhood')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_assigned')->default(false);

            // آخر حالة مسجلة للعرض السريع
            $table->float('last_temp')->nullable();
            $table->float('last_hum')->nullable();
            $table->boolean('alarm_active')->default(false);
            $table->unsignedBigInteger('last_msg_id')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transformers');
    }
};