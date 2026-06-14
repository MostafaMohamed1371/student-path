<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->string('complaints_support_phone', 32)->nullable()->after('authorized_person_phone');
            $table->string('complaints_support_whatsapp', 32)->nullable()->after('complaints_support_phone');
            $table->string('complaints_support_hours', 255)->nullable()->after('complaints_support_whatsapp');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->dropColumn([
                'complaints_support_phone',
                'complaints_support_whatsapp',
                'complaints_support_hours',
            ]);
        });
    }
};
