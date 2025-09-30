<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('group_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('group_chats')->onDelete('cascade'); // group link
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');      // kisne bheja
            $table->text('message')->nullable();
            $table->enum('type', ['text', 'image', 'voice', 'file', 'post'])->default('text');
            $table->string('media_url')->nullable();
            $table->string('media_path')->nullable();
            $table->json('read_by')->nullable();
            $table->json('deleted_by')->nullable();
            $table->string('firebase_key')->nullable();
            $table->timestamps();

            $table->index(['group_id']);
            $table->index(['sender_id']);
            $table->index(['type']);
            $table->index(['created_at']);
            $table->index(['firebase_key']);
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('group_messages');
    }
};
