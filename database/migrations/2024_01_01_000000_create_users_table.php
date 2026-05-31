<?php

use Flint\Schema;
use Flint\Blueprint;

return new class {
    public function up(PDO $pdo): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(PDO $pdo): void
    {
        Schema::dropIfExists('users');
    }
};
