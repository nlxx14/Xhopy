<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('label', 40)->default('Home');
            $table->string('recipient_name', 120);
            $table->string('phone', 32);
            $table->string('line_1', 180);
            $table->string('line_2', 180)->nullable();
            $table->string('barangay', 120)->nullable();
            $table->string('city', 120);
            $table->string('province', 120);
            $table->string('postal_code', 20);
            $table->char('country_code', 2)->default('PH');
            $table->string('delivery_notes', 255)->nullable();
            $table->boolean('is_default_shipping')->default(false);
            $table->boolean('is_default_billing')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_default_shipping']);
            $table->index(['user_id', 'is_default_billing']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
