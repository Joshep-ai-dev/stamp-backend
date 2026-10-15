<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\KrooId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'query' => ['nullable', 'string', 'max:200'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $term = Str::lower(trim($data['query'] ?? ''));
        $users = User::query()
            ->when($term !== '', function ($query) use ($term): void {
                $query->where(function ($query) use ($term): void {
                    foreach (['name', 'family_name', 'email'] as $column) {
                        $query->orWhereRaw("LOWER({$column}) LIKE ?", ['%'.$term.'%']);
                    }
                    $id = KrooId::parse($term);
                    if ($id !== null) {
                        $query->orWhere('kroo_id', $id);
                    }
                });
            })
            ->orderBy('created_at', 'desc')->orderBy('id')
            ->paginate(25, ['id', 'name', 'family_name', 'email', 'kroo_id', 'created_at']);
        $users->getCollection()->each(function (User $user): void {
            $user->setAttribute('kroo_id', KrooId::format((int) $user->kroo_id));
        });

        return response()->json($users);
    }

    public function destroy(Request $request, User $user): Response
    {
        $data = $request->validate(['confirmation' => ['required', 'string']]);
        $name = trim(collect([$user->name, $user->family_name])->filter()->join(' '));
        abort_unless(hash_equals($name, trim($data['confirmation'])), 422, 'Enter the account name to confirm deletion.');

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            if (filled($user->email)) {
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            }
            $user->delete();
        });

        return response()->noContent();
    }
}
