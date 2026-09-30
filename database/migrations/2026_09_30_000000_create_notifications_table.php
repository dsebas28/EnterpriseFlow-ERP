<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            // The company the notification is about. Users belong to several
            // companies: the bell shows the active company's notifications
            // plus personal ones (null, e.g. an invitation to a new company).
            $table->foreignUlid('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id', 'company_id', 'read_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            // {category: {database: bool, mail: bool}}; missing keys use the
            // category defaults, so new categories need no data migration.
            $table->json('notification_preferences')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_preferences');
        });

        Schema::dropIfExists('notifications');
    }
};
