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
        Schema::create('telegram_chat_list', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('telegram_account_id')->constrained()->onDelete('cascade');
            $table->bigInteger('chat_id')->unique();
            $table->string('chat_title', 255)->nullable();
            $table->string('username', 50)->nullable();
            $table->enum('chat_type', ['chat', 'user', 'channel', 'other'])->nullable();
            $table->json('status')->nullable();
            $table->string('photo', 60)->nullable();
            $table->text('last_message')->nullable();
            $table->timestamp('last_message_date')->nullable();
            $table->integer('participants_count')->default(0)->nullable();
            $table->timestamps();
        });

        Schema::table('telegram_chat_list', function (Blueprint $table) {
            $table->index('telegram_account_id');
            $table->index('chat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_chat_lists');
    }
};
