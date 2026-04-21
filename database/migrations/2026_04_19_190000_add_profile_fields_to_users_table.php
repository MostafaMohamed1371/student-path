<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('image')->nullable()->after('name');
            $table->string('city')->nullable()->after('phone');
            $table->string('licence_number')->nullable()->after('city');
            $table->unsignedInteger('votes')->default(0)->after('licence_number');
            $table->decimal('rate', 3, 1)->default(0)->after('votes');
            $table->boolean('is_verified')->default(false)->after('rate');
            $table->string('preferred_language', 2)->default('en')->after('is_verified');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'image',
                'city',
                'licence_number',
                'votes',
                'rate',
                'is_verified',
                'preferred_language',
            ]);
        });
    }
};
