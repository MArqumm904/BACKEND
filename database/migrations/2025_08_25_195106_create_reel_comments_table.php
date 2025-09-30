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
        Schema::create('reel_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reel_id');   // kis reel par comment
            $table->unsignedBigInteger('user_id');   // kis user ne comment kiya
            $table->text('comment');                 // comment text
            $table->unsignedBigInteger('parent_id')->nullable(); // reply to another comment
            $table->bigInteger('likes_count')->default(0);       // comment ke likes
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('reel_comments');
    }
};
