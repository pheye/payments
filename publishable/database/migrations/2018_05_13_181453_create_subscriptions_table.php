<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateSubscriptionsTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('subscriptions', function(Blueprint $table)
        {
            $table->increments('id');
            $table->integer('user_id')->unsigned()->index('subscriptions_user_id_foreign');
            $table->string('plan');
            $table->string('agreement_id');
            $table->integer('quantity');
            $table->dateTime('trial_ends_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->integer('coupon_id')->nullable();
            $table->float('setup_fee')->nullable();
            $table->string('frequency');
            $table->integer('frequency_interval');
            $table->string('remote_status')->nullable();
            $table->string('buyer_email')->nullable();
            $table->dateTime('next_billing_date')->nullable();
            $table->dateTime('canceled_at')->nullable();
            $table->string('skype')->nullable()->default('');
            $table->integer('gateway_id')->unsigned()->index('subscriptions_gateway_id_foreign');
            $table->string('status');
            $table->string('tag')->default('default');
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
        Schema::drop('subscriptions');
    }

}
