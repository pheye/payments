<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateGatewayConfigsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('gateway_configs', function(Blueprint $table)
		{
			$table->increments('id');
			$table->string('gateway_name', 191)->unique();
			$table->string('factory_name');
			$table->text('config');
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
		Schema::drop('gateway_configs');
	}

}
