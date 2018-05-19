<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateCouponsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('coupons', function(Blueprint $table)
		{
			$table->increments('id');
			$table->string('name');
			$table->string('code', 192)->unique();
			$table->integer('type')->default(0);
			$table->float('discount');
			$table->float('total')->nullable();
			$table->dateTime('start')->nullable();
			$table->dateTime('end')->nullable();
			$table->integer('uses')->default(0);
			$table->integer('customer_uses')->default(0);
			$table->integer('used')->default(0);
			$table->integer('status')->default(0);
            $table->integer('user_id')->unsigned()->nullable()->index('coupons_user_id_foreign');
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
		Schema::drop('coupons');
	}

}
