<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();

            $table->string('full_name');
            $table->string('gender', 16);
            $table->date('date_of_birth')->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('profile_photo')->nullable();

            $table->string('grade', 100);
            $table->string('student_phone', 20);

            $table->string('guardian_name');
            $table->string('guardian_primary_phone', 20);
            $table->string('guardian_backup_phone', 20)->nullable();
            $table->string('relationship', 100);

            $table->string('district_area');
            $table->string('nearest_landmark');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('status', 16)->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
