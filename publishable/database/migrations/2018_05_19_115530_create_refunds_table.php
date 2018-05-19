<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateRefundsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('refunds', function(Blueprint $table)
		{
			$table->float('amount');
			$table->timestamps();
			$table->increments('id');
			$table->text('note', 65535)->nullable();
			$table->bigInteger('payment_id')->unsigned()->index('refunds_payment_id_foreign');
			$table->dateTime('refunded_at')->nullable();
			$table->string('remote_number')->nullable()->unique();
			$table->string('status');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('refunds');
	}

}
