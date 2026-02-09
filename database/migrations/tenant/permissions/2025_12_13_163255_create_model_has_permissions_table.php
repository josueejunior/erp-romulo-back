<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'model_has_permissions';

    public function up(): void
    {
        $teams = config('permission.teams');
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';
        throw_if(empty($tableNames), \Exception::class, 'Error: config/permission.php not loaded.');

        Schema::create($tableNames['model_has_permissions'], static function (Blueprint $table) use ($tableNames, $columnNames, $pivotPermission, $teams) {
            $table->unsignedBigInteger($pivotPermission);
            $table->string('model_type', Blueprint::VARCHAR_DEFAULT);
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->foreign($pivotPermission)->references('id')->on($tableNames['permissions'])->onDelete('cascade');
            if ($teams) {
                $table->unsignedBigInteger($columnNames['team_foreign_key']);
                $table->index($columnNames['team_foreign_key'], 'model_has_permissions_team_foreign_key_index');
                $table->primary([$columnNames['team_foreign_key'], $pivotPermission, $columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_permission_model_type_primary');
            } else {
                $table->primary([$pivotPermission, $columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_permission_model_type_primary');
            }
        });
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');
        Schema::dropIfExists($tableNames['model_has_permissions']);
    }
};
