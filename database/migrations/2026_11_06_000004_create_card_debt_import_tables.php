<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_admin')->default(false)->after('password');
        });
        // Contas que já existiam antes desta migration viram administradoras; novas contas do cadastro público não.
        DB::table('users')->update(['is_admin' => true]);

        // Cada envio da planilha "Dívidas do Cartão" confirmado.
        Schema::create('card_debt_imports', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('file_name', 255);
            $t->char('file_hash', 64);
            $t->foreignUuid('credit_card_id')->nullable()->constrained('credit_cards')->nullOnDelete();
            $t->foreignUuid('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $t->unsignedInteger('rows_in_file')->default(0);
            $t->unsignedInteger('created_count')->default(0);
            $t->unsignedInteger('skipped_count')->default(0);
            $t->bigInteger('total_cents')->default(0);
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        // Lançamentos criados por cada importação (para desfazer).
        Schema::create('card_debt_import_items', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('card_debt_import_id')->constrained('card_debt_imports')->cascadeOnDelete();
            $t->uuid('transaction_id')->unique();
            $t->unsignedInteger('line');
        });

        DB::statement('CREATE TRIGGER card_debt_imports_audit_trigger AFTER INSERT OR UPDATE OR DELETE ON card_debt_imports FOR EACH ROW EXECUTE FUNCTION financaplus_capture_changes()');
    }

    public function down(): void
    {
        Schema::dropIfExists('card_debt_import_items');
        Schema::dropIfExists('card_debt_imports');
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('is_admin');
        });
    }
};
