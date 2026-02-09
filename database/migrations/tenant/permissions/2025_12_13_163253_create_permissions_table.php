<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'permissions';

    public function up(): void
    {
        $tableNames = config('permission.table_names');
        throw_if(empty($tableNames), \Exception::class, 'Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.');

        Schema::create($tableNames['permissions'], static function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', Blueprint::VARCHAR_DEFAULT);
            $table->string('guard_name', Blueprint::VARCHAR_SMALL);
            $table->datetimes();
            $table->unique(['name', 'guard_name']);
        });
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');
        Schema::dropIfExists($tableNames['permissions']);
    }
};
