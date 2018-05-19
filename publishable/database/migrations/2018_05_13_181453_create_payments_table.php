<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreatePaymentsTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payments', function(Blueprint $table)
        {
            $table->bigInteger('id', true)->unsigned();
            $table->text('details', 65535);
            $table->string('number')->unique();
            $table->string('description');
            $table->string('client_id');
            $table->string('client_email');
            $table->string('amount');
            $table->string('currency');
            $table->string('status');
            $table->integer('subscription_id')->unsigned()->index('payments_subscription_id_foreign');
            $table->string('buyer_email')->nullable();
            $table->bigInteger('invoice_id')->nullable();
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
        Schema::drop('payments');
    }

}
