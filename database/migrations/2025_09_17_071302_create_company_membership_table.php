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
        Schema::create('company_membership', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('page_id');
            $table->unsignedBigInteger('user_page_id');
            $table->string('company_name');
            $table->string('job_title');
            $table->string('location');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('currently_working')->default(false);
            $table->text('responsibilities')->nullable();
            $table->enum('status', [
                'pending',           // user ne request send ki
                'company_approved',  // company ne approve kiya means user ny approve krdya
                'admin_verified',    // admin ne final verify kiya
                'rejected'           // kahin se bhi reject
            ])->default('pending');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('company_membership');
    }
};
