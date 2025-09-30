<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sender_id');
            $table->unsignedBigInteger('receiver_id');
            $table->text('message');
            $table->enum('type', ['text', 'image', 'voice', 'file', 'post'])->default('text');
            $table->string('media_url')->nullable();
            $table->string('media_path')->nullable();
            $table->json('deleted_by')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            // Helpful indexes
            $table->index(['sender_id', 'receiver_id']);
            $table->index(['type']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('messages');
    }
};
