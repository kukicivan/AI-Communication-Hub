<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class UserProfileController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/profile",
     *     summary="Get user profile",
     *     tags={"User Profile"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="User profile information",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function getProfile(Request $request)
    {
        // Load the user with their roles and permissions
        $user = $request->user()->load(['roles', 'permissions', 'userType']);

        return response()->json([
            'user' => $user
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/profile",
     *     summary="Update user profile",
     *     tags={"User Profile"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="phone", type="string", example="123-456-7890"),
     *             @OA\Property(property="address_line_1", type="string", example="123 Main St"),
     *             @OA\Property(property="address_line_2", type="string", example="Apt 4B"),
     *             @OA\Property(property="address_line_3", type="string", example="Building C"),
     *             @OA\Property(property="city", type="string", example="New York"),
     *             @OA\Property(property="state", type="string", example="NY"),
     *             @OA\Property(property="postal_code", type="string", example="10001"),
     *             @OA\Property(property="country", type="string", example="USA"),
     *             @OA\Property(property="bio", type="string", example="A short bio about me")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object"),
     *             @OA\Property(property="message", type="string", example="Profile updated successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
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

        $user = $request->user();
        $user->fill($request->only([
            'name', 'phone', 'address_line_1', 'address_line_2', 'address_line_3',
            'city', 'state', 'postal_code', 'country', 'bio'
        ]));
        $user->save();

        return response()->json([
            'user' => $user->fresh(),
            'message' => 'Profile updated successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/profile/avatar",
     *     summary="Upload user avatar",
     *     tags={"User Profile"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="avatar",
     *                     type="string",
     *                     format="binary",
     *                     description="The avatar image file"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Avatar uploaded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object"),
     *             @OA\Property(property="message", type="string", example="Avatar uploaded successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function uploadAvatar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        // Delete old avatar if exists
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        // Process and save the new avatar
        if ($request->hasFile('avatar')) {
            $image = $request->file('avatar');
            $filename = 'avatars/' . time() . '.' . $image->getClientOriginalExtension();

            // Resize and save image
            $img = Image::make($image->getRealPath());
            $img->fit(300, 300);

            // Save to storage
            Storage::disk('public')->put($filename, $img->stream());

            $user->avatar = $filename;
            $user->save();
        }

        return response()->json([
            'user' => $user->fresh(),
            'message' => 'Avatar uploaded successfully'
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/profile/avatar",
     *     summary="Delete user avatar",
     *     tags={"User Profile"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Avatar deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object"),
     *             @OA\Property(property="message", type="string", example="Avatar deleted successfully")
     *         )
     *     )
     * )
     */
    public function deleteAvatar(Request $request)
    {
        $user = $request->user();

        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->avatar = null;
        $user->save();

        return response()->json([
            'user' => $user->fresh(),
            'message' => 'Avatar deleted successfully'
        ]);
    }
}
