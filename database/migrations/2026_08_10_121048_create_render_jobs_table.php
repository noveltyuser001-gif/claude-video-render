<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('render_jobs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('template_id')->nullable();

            $table->string('doctor_name')->nullable();
            $table->string('hospital_name')->nullable();
            $table->string('photo_path')->nullable();

            $table->string('output_video')->nullable();

            $table->string('status')->default('pending');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index('template_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('render_jobs');
    }
};