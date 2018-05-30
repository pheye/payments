<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePayumTokensTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
		\Schema::create('payum_tokens', function($table) {
			/** @var \Illuminate\Database\Schema\Blueprint $table */
			$table->string('hash')->primary();
			$table->text('details');
			$table->string('targetUrl');
			$table->string('afterUrl');
			$table->string('gatewayName');
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
        //
    }
}
