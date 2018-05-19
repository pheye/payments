<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeysToCouponsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('coupons', function(Blueprint $table)
		{
			$table->foreign('user_id')->references('id')->on('users')->onUpdate('NO ACTION')->onDelete('CASCADE');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('coupons', function(Blueprint $table)
		{
			$table->dropForeign('coupons_user_id_foreign');
		});
	}

}
