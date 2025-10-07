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
        Schema::create('group_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id');  // group id manually insert hoga
            $table->unsignedBigInteger('user_id');   // user id manually insert hoga
            $table->enum('role', ['member', 'admin'])->default('member'); // user ka role
            $table->enum('status', ['joined', 'pending', 'rejected'])->default('pending'); // membership status
            $table->timestamp('joined_at')->nullable(); // joined date
            $table->timestamps(); // created_at & updated_at
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('group_members');
    }
};
