<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_texts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('story_id'); // Story ka ID (no FK)
            $table->text('text_content'); // Text ka content
            $table->float('x_position')->default(0); // X axis position
            $table->float('y_position')->default(0); // Y axis position
            $table->integer('order_index')->default(0); // Layer order
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_texts');
    }
};
