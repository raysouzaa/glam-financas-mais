<?php
use App\Support\CategoriasPadrao;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cada conta de usuário passa a ter o próprio financeiro: pessoas, categorias, contas, cartões e
 * lançamentos ganham user_id (o dono). Os dados que já existiam ficam com o admin mais antigo
 * (`finance:transferir-dados` move tudo para outra conta depois).
 *
 * - transactions.person_id vira opcional: sem pessoa, o lançamento é do próprio dono.
 * - Nome e CPF de pessoa e nome de categoria passam a ser únicos por dono, não no sistema todo.
 * - account_links guarda a decisão de quem tem o CPF cadastrado por outra conta: aceitar ou remover
 *   o vínculo com aquele dono. Sem linha = pedido pendente.
 */
return new class extends Migration {
    private const TABELAS = ['people', 'categories', 'financial_accounts', 'credit_cards', 'transactions'];

    public function up(): void
    {
        foreach (self::TABELAS as $tabela) {
            Schema::table($tabela, function (Blueprint $t) {
                $t->foreignId('user_id')->nullable()->constrained('users');
            });
        }

        $dono = DB::table('users')->where('is_admin', true)->min('id') ?? DB::table('users')->min('id');
        foreach (self::TABELAS as $tabela) {
            if ($dono === null && DB::table($tabela)->exists()) {
                throw new RuntimeException("Há registros em {$tabela}, mas nenhum usuário para ser o dono. Crie uma conta (finance:create-admin) e rode a migration de novo.");
            }
            DB::table($tabela)->update(['user_id' => $dono]);
            DB::statement("ALTER TABLE {$tabela} ALTER COLUMN user_id SET NOT NULL");
            DB::statement("CREATE INDEX {$tabela}_user_id_index ON {$tabela} (user_id)");
        }
        if ($dono !== null) DB::table('card_debt_imports')->whereNull('user_id')->update(['user_id' => $dono]);

        Schema::table('people', function (Blueprint $t) {
            $t->dropUnique(['name']);
            $t->dropUnique(['cpf']);
            $t->unique(['user_id', 'name']);
            $t->unique(['user_id', 'cpf']);
        });
        Schema::table('categories', function (Blueprint $t) {
            $t->dropUnique(['name', 'type']);
            $t->unique(['user_id', 'name', 'type']);
        });

        DB::statement('ALTER TABLE transactions ALTER COLUMN person_id DROP NOT NULL');

        Schema::create('account_links', function (Blueprint $t) {
            $t->id();
            // Quem cadastrou a pessoa com o CPF.
            $t->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            // A conta que tem esse CPF e decide se quer ver os lançamentos.
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->enum('status', ['accepted', 'removed']);
            $t->timestamps();
            $t->unique(['owner_user_id', 'user_id']);
        });
        DB::statement('CREATE TRIGGER account_links_audit_trigger AFTER INSERT OR UPDATE OR DELETE ON account_links FOR EACH ROW EXECUTE FUNCTION financaplus_capture_changes()');

        // Contas que já existem e não ficaram com os dados antigos começam com as categorias padrão.
        foreach (DB::table('users')->pluck('id') as $id) {
            if (! DB::table('categories')->where('user_id', $id)->exists()) CategoriasPadrao::criarPara((int) $id);
        }
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS account_links_audit_trigger ON account_links');
        Schema::dropIfExists('account_links');
        // A volta só funciona enquanto não houver lançamento sem pessoa nem nomes repetidos entre contas.
        DB::statement('ALTER TABLE transactions ALTER COLUMN person_id SET NOT NULL');
        Schema::table('categories', function (Blueprint $t) {
            $t->dropUnique(['user_id', 'name', 'type']);
            $t->unique(['name', 'type']);
        });
        Schema::table('people', function (Blueprint $t) {
            $t->dropUnique(['user_id', 'name']);
            $t->dropUnique(['user_id', 'cpf']);
            $t->unique('name');
            $t->unique('cpf');
        });
        foreach (self::TABELAS as $tabela) {
            Schema::table($tabela, function (Blueprint $t) {
                $t->dropConstrainedForeignId('user_id');
            });
        }
    }
};
