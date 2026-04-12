<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->onDelete('cascade');
            $table->string('driver'); // Социальная сеть или сервис (например, telegram, whatsapp, vk)
            $table->string('identity'); // Уникальный идентификатор пользователя в соцсети или сервисе
            $table->string('username')->nullable(); // Имя пользователя
            $table->string('email')->nullable(); // Электронная почта
            $table->string('avatar', 1000)->nullable(); // Ссылка на аватар
            $table->timestamps();

            $table->unique(['driver', 'identity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
