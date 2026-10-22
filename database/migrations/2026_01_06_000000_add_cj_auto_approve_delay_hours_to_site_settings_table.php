<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->integer('cj_auto_approve_delay_hours')->default(0);
        });
    }

    public function down()
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn('cj_auto_approve_delay_hours');
        });
    }
};
