<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSypoLivexImportItemTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sypo_livex_import_item', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sypo_livex_import_id');
            $table->unsignedInteger('product_id');
            $table->timestamps();
			
			$table->index('product_id');
			$table->foreign('sypo_livex_import_id')->references('id')->on('sypo_livex_import');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sypo_livex_import_item');
    }
}
