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
        Schema::create('reel_likes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reel_id');   // kis reel ko like kia
            $table->unsignedBigInteger('user_id');   // kis user ne like kia
            $table->timestamps();

            // Unique constraint taake ek user ek reel ko sirf ek dafa like kar sake
            $table->unique(['reel_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('reel_likes');
    }
};
