<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table): void {
            $table->unsignedBigInteger('monthly_subscription_price')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table): void {
            $table->dropColumn('monthly_subscription_price');
        });
    }
};
