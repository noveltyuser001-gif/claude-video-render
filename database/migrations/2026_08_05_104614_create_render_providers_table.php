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
      Schema::create('render_providers', function(Blueprint $table){

$table->id();

$table->string('name');

$table->string('driver');

$table->boolean('active')
->default(false);

$table->integer('priority')
->default(1);

$table->timestamps();

});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('render_providers');
    }
};
