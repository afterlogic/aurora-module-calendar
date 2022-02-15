<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;
use Aurora\System\Classes\Encoding;

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
        Capsule::statement("SET NAMES latin1");
        Capsule::statement("UPDATE {$prefix}adav_calendarinstances SET displayname = CONVERT(cast(CONVERT(displayname USING latin1) AS BINARY) USING utf8)
        WHERE CONVERT(displayname USING latin1) = CONVERT(displayname USING utf8) AND LENGTH(displayname) != CHAR_LENGTH(displayname)");
        Capsule::statement("SET NAMES utf8");
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
