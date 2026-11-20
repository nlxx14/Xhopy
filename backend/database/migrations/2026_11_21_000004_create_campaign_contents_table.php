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
        Schema::create('campaign_contents', function (Blueprint $table) {
            $table->id();
            $table->string('placement', 40);
            $table->string('tag', 60);
            $table->string('title', 160);
            $table->string('summary', 400);
            $table->string('cta_label', 60);
            $table->string('href', 255);
            $table->string('image_path')->nullable();
            $table->string('image_alt', 200)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['placement', 'is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_contents');
    }
};
