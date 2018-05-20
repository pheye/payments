<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreatePlansTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        /**
         * 对于我们大部分的系统，支付系统通常会引起角色的变化
         * 但这个不应该是属于支付包的功能，在支付系统中与角色关联
         * 会产生过大的耦合。此处为了方便放了role_id
         */
        Schema::create('plans', function(Blueprint $table)
        {
            $table->increments('id');
            $table->integer('role_id')->unsigned();
            $table->string('name');
            $table->string('display_name');
            $table->string('desc');
            $table->string('type');
            $table->string('frequency');
            $table->integer('frequency_interval');
            $table->integer('cycles');
            $table->float('amount');
            $table->string('currency');
            $table->string('paypal_id')->nullable();
            $table->float('setup_fee')->nullable();
            $table->integer('display_order');
            $table->integer('delay_days')->default(0);
            $table->timestamps();

            /* $table->foreign('role_id')->references('id')->on('roles'); */
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('plans');
    }

}
