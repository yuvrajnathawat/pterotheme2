<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddSortOrderToNodesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->integer('sort')->unsigned()->default(0)->after('id');
        });

        // Initialize sort order to match the current node ordering.
        $nodes = DB::table('nodes')->orderBy('id')->pluck('id');
        foreach ($nodes as $index => $id) {
            DB::table('nodes')->where('id', $id)->update(['sort' => $index + 1]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn('sort');
        });
    }
}
