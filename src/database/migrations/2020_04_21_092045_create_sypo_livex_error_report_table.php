<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSypoLivexErrorReportTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sypo_livex_error_report', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('admin_id')->nullable();
            $table->unsignedInteger('order_id')->nullable();
            $table->string('code');
            $table->integer('line')->unsigned()->default(0);
            $table->text('message');
            $table->timestamps();
			
			$table->index('code');
			$table->foreign('admin_id')->references('id')->on('admins');
			$table->foreign('order_id')->references('id')->on('orders');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sypo_livex_error_report');
    }
}
