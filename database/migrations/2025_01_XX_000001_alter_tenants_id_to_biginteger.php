<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Primeiro, remover foreign keys que referenciam tenant_id como string
        // Depois alterar a coluna id para bigInteger
        // E recriar as foreign keys
        
        Schema::table('domains', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
        });
        
        Schema::table('assinaturas', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
        });
        
        // Alterar coluna id de string para bigInteger auto-increment
        DB::statement('ALTER TABLE tenants DROP CONSTRAINT tenants_pkey');
        DB::statement('ALTER TABLE tenants ALTER COLUMN id TYPE BIGINT USING id::bigint');
        DB::statement('CREATE SEQUENCE tenants_id_seq OWNED BY tenants.id');
        DB::statement('ALTER TABLE tenants ALTER COLUMN id SET DEFAULT nextval(\'tenants_id_seq\')');
        DB::statement('ALTER TABLE tenants ADD PRIMARY KEY (id)');
        
        // Atualizar foreign keys para usar bigInteger
        Schema::table('domains', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->change();
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');
        });
        
        Schema::table('assinaturas', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->change();
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverter para string
        Schema::table('domains', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
        });
        
        Schema::table('assinaturas', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
        });
        
        DB::statement('ALTER TABLE tenants DROP CONSTRAINT tenants_pkey');
        DB::statement('DROP SEQUENCE IF EXISTS tenants_id_seq');
        DB::statement('ALTER TABLE tenants ALTER COLUMN id TYPE VARCHAR(255)');
        DB::statement('ALTER TABLE tenants ADD PRIMARY KEY (id)');
        
        Schema::table('domains', function (Blueprint $table) {
            $table->string('tenant_id', 255)->change();
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');
        });
        
        Schema::table('assinaturas', function (Blueprint $table) {
            $table->string('tenant_id', 255)->change();
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');
        });
    }
};

