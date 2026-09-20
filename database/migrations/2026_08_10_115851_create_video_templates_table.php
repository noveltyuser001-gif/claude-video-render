<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_templates', function (Blueprint $table) {
            $table->id();

            $table->string('source')->default('local');
            $table->string('source_template_id')->nullable()->index();
            $table->integer('version')->default(1);

            $table->string('name');
            $table->string('category')->nullable();

            $table->string('video_path');
            $table->string('thumbnail')->nullable();

            $table->integer('width')->default(1080);
            $table->integer('height')->default(1920);
            $table->integer('fps')->default(30);

            $table->string('bitrate')->default('4M');

            $table->decimal('duration', 8, 2)->nullable();

            $table->string('output_format')->default('mp4');
            $table->string('video_codec')->default('libx264');
            $table->string('audio_codec')->default('aac');

            $table->boolean('active')->default(true);

            $table->json('settings')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_templates');
    }
};