<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('investigator_company', function (Blueprint $table) {
            // Both Investigator and Company use ULID string primary keys (string, 26)
            $table->string('investigator_id', 26);
            $table->string('company_id', 26);
            $table->timestamps();

            $table->primary(['investigator_id', 'company_id']);

            $table->foreign('investigator_id')
                ->references('id')
                ->on('investigators')
                ->cascadeOnDelete();

            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('investigator_company');
    }
};
