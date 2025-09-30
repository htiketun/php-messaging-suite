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
        Schema::create('telegram_chat_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('telegram_account_id')->constrained()->onDelete('cascade');
            $table->bigInteger('chat_id');
            $table->bigInteger('message_id');
            $table->bigInteger('sender_id')->nullable();
            $table->text('content')->nullable();
            $table->boolean('is_outgoing')->default(false);
            $table->json('additional_data')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamp('timestamp')->nullable();
            $table->timestamps();
        });

        Schema::table('telegram_chat_histories', function (Blueprint $table) {
            $table->index('telegram_account_id');
            $table->index('chat_id');
            $table->index('message_id');
            $table->index('sender_id');
            $table->index('timestamp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_chat_histories');
    }
};
