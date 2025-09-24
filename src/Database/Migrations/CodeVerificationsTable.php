<?php

namespace XWMS\Package\Database\Migrations;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class CodeVerificationsTable
{
    public function up()
    {
        Schema::create('code_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->text('ip')->nullable();
            $table->text('category');
            $table->text('code');
            
            $table->text('status');
            $table->text('email')->nullable();
            $table->integer('attempt')->default(1);

            $table->timestamp('last_attempt')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('code_verifications');
    }
}
