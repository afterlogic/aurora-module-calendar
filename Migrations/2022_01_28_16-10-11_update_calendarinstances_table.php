<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class UpdateCalendarinstancesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $prefix = Capsule::connection()->getTablePrefix();
        Capsule::statement("UPDATE {$prefix}adav_calendarinstances SET displayname = CONVERT(cast(CONVERT(displayname USING latin1) AS BINARY) USING utf8)");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $prefix = Capsule::connection()->getTablePrefix();
        Capsule::statement("UPDATE {$prefix}adav_calendarinstances SET displayname = CONVERT(cast(CONVERT(displayname USING utf8) AS BINARY) USING latin1)");
    }
}
