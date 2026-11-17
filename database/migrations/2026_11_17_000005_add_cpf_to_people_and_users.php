<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CPF (só dígitos, 11 caracteres) em pessoas e usuários.
 * O vínculo usuário → pessoa é feito por esse campo (User::pessoa()), sem chave estrangeira:
 * basta o admin informar o CPF no cadastro da pessoa para o usuário passar a ver as dívidas dela.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('people', function (Blueprint $t) {
            $t->string('cpf', 11)->nullable()->unique()->after('name');
        });
        Schema::table('users', function (Blueprint $t) {
            $t->string('cpf', 11)->nullable()->unique()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropUnique(['cpf']);
            $t->dropColumn('cpf');
        });
        Schema::table('people', function (Blueprint $t) {
            $t->dropUnique(['cpf']);
            $t->dropColumn('cpf');
        });
    }
};
