<?php

namespace XWMS\Package\Database\Migrations;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class CountryMigration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('short_name');
            $table->string('name');
            $table->string('phonecode')->nullable();
            $table->boolean('is_eu_member')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
}
