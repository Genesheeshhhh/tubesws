<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->string('drivetrain', 3)->nullable()->after('transmission');
        });

        foreach ([
            'showcase-toyota-avanza-1-3-g' => 'RWD',
            'showcase-honda-brio-e-cvt-sat' => 'FWD',
            'showcase-mitsubishi-pajero-sport-4x4' => '4WD',
            'showcase-suzuki-ignis-rs-2022' => 'FWD',
        ] as $slug => $drivetrain) {
            DB::table('cars')
                ->where('slug', $slug)
                ->where('source', 'showcase')
                ->update(['drivetrain' => $drivetrain]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->dropColumn('drivetrain');
        });
    }
};
