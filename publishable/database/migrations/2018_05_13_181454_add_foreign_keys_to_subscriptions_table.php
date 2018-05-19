<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeysToSubscriptionsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('subscriptions', function(Blueprint $table)
		{
			$table->foreign('gateway_id')->references('id')->on('gateway_configs')->onUpdate('NO ACTION')->onDelete('NO ACTION');
			$table->foreign('user_id')->references('id')->on('users')->onUpdate('NO ACTION')->onDelete('NO ACTION');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('subscriptions', function(Blueprint $table)
		{
			$table->dropForeign('subscriptions_gateway_id_foreign');
			$table->dropForeign('subscriptions_user_id_foreign');
		});
	}

}
