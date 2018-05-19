<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeysToRefundsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('refunds', function(Blueprint $table)
		{
			$table->foreign('payment_id')->references('id')->on('payments')->onUpdate('NO ACTION')->onDelete('NO ACTION');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('refunds', function(Blueprint $table)
		{
			$table->dropForeign('refunds_payment_id_foreign');
		});
	}

}
