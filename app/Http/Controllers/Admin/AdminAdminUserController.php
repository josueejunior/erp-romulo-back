<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Auth\Models\AdminUser;
use App\Support\Logging\AdminLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Gerenciamento de usuários administradores (tabela admin_users no banco CENTRAL).
 *
 * ATENÇÃO: não tem relação com os usuários de tenants.
 */
class AdminAdminUserController extends Controller
{
    use AdminLogger;

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 20);
        $perPage = $perPage > 100 ? 100 : $perPage;

        $query = AdminUser::query()->orderBy('id', 'asc');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', '%' . $search . '%')
                    ->orWhere('email', 'ilike', '%' . $search . '%');
            });
        }

        $paginado = $query->paginate($perPage);

        return ApiResponse::collection($paginado->items(), [
            'total'        => $paginado->total(),
            'per_page'     => $paginado->perPage(),
            'current_page' => $paginado->currentPage(),
            'last_page'    => $paginado->lastPage(),
        ]);
    }

    public function show(int $id)
    {
        $admin = AdminUser::find($id);
        if (!$admin) {
            return ApiResponse::error('Admin não encontrado.', 404);
        }

        return ApiResponse::item($admin);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:admin_users,email'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $admin = AdminUser::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $this->auditAdminAction('admin_user.created', [
            'resource_type' => 'admin_user',
            'resource_id'   => $admin->id,
            'admin_email'   => $admin->email,
        ]);

        return ApiResponse::item($admin, 201);
    }

    public function update(Request $request, int $id)
    {
        $admin = AdminUser::find($id);
        if (!$admin) {
            return ApiResponse::error('Admin não encontrado.', 404);
        }

        $data = $request->validate([
            'name'     => ['sometimes', 'required', 'string', 'max:255'],
            'email'    => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('admin_users', 'email')->ignore($admin->id),
            ],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        if (isset($data['name'])) {
            $admin->name = $data['name'];
        }
        if (isset($data['email'])) {
            $admin->email = $data['email'];
        }
        if (!empty($data['password'])) {
            $admin->password = Hash::make($data['password']);
        }

        $admin->save();

        $this->auditAdminAction('admin_user.updated', [
            'resource_type' => 'admin_user',
            'resource_id'   => $admin->id,
            'admin_email'   => $admin->email,
        ]);

        return ApiResponse::item($admin);
    }

    public function destroy(int $id)
    {
        $admin = AdminUser::find($id);
        if (!$admin) {
            return ApiResponse::error('Admin não encontrado.', 404);
        }

        $adminId = $admin->id;
        $adminEmail = $admin->email;

        $admin->delete();

        $this->auditAdminAction('admin_user.deleted', [
            'resource_type' => 'admin_user',
            'resource_id'   => $adminId,
            'admin_email'   => $adminEmail,
        ]);

        return ApiResponse::success('Admin removido com sucesso.');
    }
}

