<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_elements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('template_id')
                ->constrained('video_templates')
                ->cascadeOnDelete();

            $table->string('source_element_id')->nullable();

            $table->string('name');
            $table->string('field');
            $table->string('type');

            $table->integer('x')->default(0);
            $table->integer('y')->default(0);

            $table->integer('width')->nullable();
            $table->integer('height')->nullable();

            $table->unsignedBigInteger('font_id')->nullable();
            $table->unsignedBigInteger('media_file_id')->nullable();

            $table->integer('opacity')->default(100);

            $table->decimal('rotation', 8, 2)->default(0);

            $table->integer('z_index')->default(1);

            $table->boolean('visible')->default(true);
            $table->boolean('locked')->default(false);

            $table->json('settings')->nullable();

            $table->timestamps();

            $table->index('field');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_elements');
    }
};