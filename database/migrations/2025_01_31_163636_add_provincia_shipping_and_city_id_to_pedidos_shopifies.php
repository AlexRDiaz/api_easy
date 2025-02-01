<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pedidos_shopifies', function (Blueprint $table) {
            $table->string('provincia_shipping', 255)->nullable()->after('ciudad_shipping');
            $table->unsignedInteger('city_id')->nullable()->after('provincia_shipping');

            $table->foreign('city_id')->references('id')->on('coverage_external')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pedidos_shopifies', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
            $table->dropColumn(['provincia_shipping', 'city_id']);
        });
    }
};
