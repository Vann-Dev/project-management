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
        // Add order column to ticket_statuses if it doesn't exist
        if (!Schema::hasColumn('ticket_statuses', 'order')) {
            Schema::table('ticket_statuses', function (Blueprint $table) {
                $table->integer('order')->default(0)->after('is_default');
            });
        }

        // Add order column to ticket_types if it doesn't exist
        if (!Schema::hasColumn('ticket_types', 'order')) {
            Schema::table('ticket_types', function (Blueprint $table) {
                $table->integer('order')->default(0)->after('is_default');
            });
        }

        // Add order column to ticket_priorities if it doesn't exist
        if (!Schema::hasColumn('ticket_priorities', 'order')) {
            Schema::table('ticket_priorities', function (Blueprint $table) {
                $table->integer('order')->default(0)->after('is_default');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ticket_statuses', function (Blueprint $table) {
            $table->dropColumn('order');
        });

        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropColumn('order');
        });

        Schema::table('ticket_priorities', function (Blueprint $table) {
            $table->dropColumn('order');
        });
    }
};
