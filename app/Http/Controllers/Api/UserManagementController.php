<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    use AuthorizesRequests;
    /**
        * @OA\Get(
        *     path="/api/users",
        *     summary="Get all users",
        *     tags={"User Management"},
        *     security={{"sanctum":{}}},
        *     @OA\Parameter(
        *         name="page",
        *         in="query",
        *         description="Page number",
        *         required=false,
        *         @OA\Schema(type="integer", default=1)
        *     ),
        *     @OA\Parameter(
        *         name="per_page",
        *         in="query",
        *         description="Items per page",
        *         required=false,
        *         @OA\Schema(type="integer", default=15)
        *     ),
        *     @OA\Parameter(
        *         name="search",
        *         in="query",
        *         description="Search term",
        *         required=false,
        *         @OA\Schema(type="string")
        *     ),
        *     @OA\Response(
        *         response=200,
        *         description="List of users",
        *         @OA\JsonContent(
        *             @OA\Property(property="users", type="object")
        *         )
        *     ),
        *     @OA\Response(
        *         response=401,
        *         description="Unauthenticated"
        *     ),
        *     @OA\Response(
        *         response=403,
        *         description="Forbidden"
        *     )
        * )
        */
    public function index(Request $request)
    {
        $this->authorize('view users');

        $perPage = $request->get('per_page', 15);
        $search = $request->get('search', '');

        $users = User::with(['roles', 'userType'])
            ->when($search, function ($query, $search) {
                return $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->paginate($perPage);

        return response()->json([
            'users' => $users->items(),
            'pagination' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage()
            ]
        ]);
    }
    /**
     * @OA\Get(
     *     path="/api/users/{id}",
     *     summary="Get user by ID",
     *     tags={"User Management"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User details",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     )
     * )
     */
    public function show($id)
    {
        $this->authorize('view users');

        $user = User::with(['roles', 'userType'])->findOrFail($id);

        return response()->json([
            'user' => $user
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/users",
     *     summary="Create a new user",
     *     tags={"User Management"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password","password_confirmation","user_type_id"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="StrongPassword123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="StrongPassword123"),
     *             @OA\Property(property="user_type_id", type="integer", example=3),
     *             @OA\Property(property="phone", type="string", example="123-456-7890"),
     *             @OA\Property(property="address_line_1", type="string", example="123 Main St"),
     *             @OA\Property(property="city", type="string", example="New York"),
     *             @OA\Property(property="country", type="string", example="USA")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object"),
     *             @OA\Property(property="message", type="string", example="User created successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     )
     * )
     */
    public function store(Request $request)
    {
        $this->authorize('create users');

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'user_type_id' => 'required|exists:user_types,id',
            'phone' => 'nullable|string|max:20',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'address_line_3' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'bio' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'user_type_id' => $request->user_type_id,
            'phone' => $request->phone,
            'address_line_1' => $request->address_line_1,
            'address_line_2' => $request->address_line_2,
            'address_line_3' => $request->address_line_3,
            'city' => $request->city,
            'state' => $request->state,
            'postal_code' => $request->postal_code,
            'country' => $request->country,
            'bio' => $request->bio,
            'email_verified_at' => now(),  // Admin-created accounts are pre-verified
        ]);

        // Assign role based on user type
        $userType = UserType::find($request->user_type_id);
        if ($userType) {
            $user->assignRole($userType->name);
        }

        return response()->json([
            'user' => $user->load(['roles', 'userType']),
            'message' => 'User created successfully'
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/users/{id}",
     *     summary="Update a user",
     *     tags={"User Management"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="user_type_id", type="integer", example=3),
     *             @OA\Property(property="phone", type="string", example="123-456-7890"),
     *             @OA\Property(property="address_line_1", type="string", example="123 Main St")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object"),
     *             @OA\Property(property="message", type="string", example="User updated successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $this->authorize('edit users');

        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $id,
            'user_type_id' => 'sometimes|required|exists:user_types,id',
            'phone' => 'nullable|string|max:20',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'address_line_3' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'bio' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        // Update user data
        $user->fill($request->only([
            'name', 'email', 'user_type_id', 'phone',
            'address_line_1', 'address_line_2', 'address_line_3',
            'city', 'state', 'postal_code', 'country', 'bio'
        ]));

        // If user type changed, update roles
        if ($request->has('user_type_id') && $user->isDirty('user_type_id')) {
            // Remove all current roles
            $user->syncRoles([]);

            // Assign new role based on user type
            $userType = UserType::find($request->user_type_id);
            if ($userType) {
                $user->assignRole($userType->name);
            }
        }

        $user->save();

        return response()->json([
            'user' => $user->fresh()->load(['roles', 'userType']),
            'message' => 'User updated successfully'
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/users/{id}",
     *     summary="Delete a user",
     *     tags={"User Management"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="User deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     )
     * )
     */
    public function destroy($id)
    {
        $this->authorize('delete users');

        $user = User::findOrFail($id);

        // Prevent deleting yourself
        if (auth()->id() === (int)$id) {
            return response()->json([
                'message' => 'You cannot delete your own account'
            ], 403);
        }

        // Delete user avatar if exists
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->tokens()->delete(); // Delete all tokens
        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/users/{id}/reset-password",
     *     summary="Reset user password (admin function)",
     *     tags={"User Management"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"password","password_confirmation"},
     *             @OA\Property(property="password", type="string", format="password", example="NewStrongPassword123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="NewStrongPassword123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password reset successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Password reset successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     )
     * )
     */
    public function resetPassword(Request $request, $id)
    {
        $this->authorize('edit users');

        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($id);
        $user->password = Hash::make($request->password);
        $user->save();

        // Revoke all tokens
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password reset successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/user-types",
     *     summary="Get all user types",
     *     tags={"User Management"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of user types",
     *         @OA\JsonContent(
     *             @OA\Property(property="userTypes", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function getUserTypes()
    {
        $userTypes = UserType::all();

        return response()->json([
            'userTypes' => $userTypes
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/roles",
     *     summary="Get all roles",
     *     tags={"User Management"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of roles",
     *         @OA\JsonContent(
     *             @OA\Property(property="roles", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     )
     * )
     */
    public function getRoles()
    {
        $this->authorize('view users');

        $roles = Role::with('permissions')->get();

        return response()->json([
            'roles' => $roles
        ]);
    }
}
