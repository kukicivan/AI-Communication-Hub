<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\UserType;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;

class AuthController extends BaseController
{

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
            'c_password' => 'required|same:password',
        ]);

        if($validator->fails()){
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        // Default to a regular user type if not specified
        $userTypeId = $request->user_type_id;
        if (!$userTypeId) {
            $userType = UserType::where('name', 'tourism_organization')->first();
            $userTypeId = $userType ? $userType->id : null;
        }

        $input = $request->all();
        $input['password'] = bcrypt($input['password']);
        $input['user_type_id'] = $userTypeId;

        $user = User::create($input);

        // Assign role based on user type
//        $userType = UserType::find($userTypeId);
//        if ($userType) {
//            $user->assignRole($userType->name);
//        }

        $token = Auth::login($user);
        $success = $this->respondWithToken($token);
        $success['user'] = $user;

        return $this->sendResponse($success, 'User registered successfully.');
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if($validator->fails()){
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $credentials = request(['email', 'password']);

        if (!$token = auth()->attempt($credentials)) {
            return $this->sendError('Unauthorized.', ['error'=>'Invalid credentials'], 401);
        }

        $success = $this->respondWithToken($token);
        $success['user'] = auth()->user();

        return $this->sendResponse($success, 'User login successfully.');
    }

    public function profile()
    {
        $success = auth()->user();
        return $this->sendResponse($success, 'User profile retrieved successfully.');
    }

    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return $this->sendResponse([], 'Successfully logged out.');
        } catch (JWTException $exception) {
            return $this->sendError('Sorry, the user cannot be logged out', [], 500);
        }
    }

    public function refresh()
    {
        try {
            $token = JWTAuth::getToken();
            if (!$token) {
                return $this->sendError('Token not provided', [], 401);
            }

            $newToken = JWTAuth::refresh($token);
            $success = $this->respondWithToken($newToken);

            return $this->sendResponse($success, 'Token refreshed successfully.');
        } catch (TokenExpiredException $e) {
            return $this->sendError('Token has expired and cannot be refreshed', [], 401);
        } catch (TokenInvalidException $e) {
            return $this->sendError('Token is invalid', [], 401);
        } catch (JWTException $e) {
            return $this->sendError('Token cannot be refreshed', [], 500);
        }
    }

    public function me()
    {
        return $this->sendResponse(auth()->user(), 'User information retrieved successfully.');
    }

    protected function respondWithToken($token)
    {
        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            'refresh_token' => $token // In production, implement separate refresh token
        ];
    }


    /**
     * OLDER CODE - WORKING changePassword
     */


    /**
     * @OA\Post(
     *     path="/api/auth/email/verification-notification",
     *     summary="Resend email verification link",
     *     tags={"Authentication"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Verification link sent",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Verification link sent")
     *         )
     *     )
     * )
     */
    public function sendVerificationEmail(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified'
            ]);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Verification link sent'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/auth/email/verify/{id}/{hash}",
     *     summary="Verify email",
     *     tags={"Authentication"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="hash",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Email verified",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Email verified successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid verification link"
     *     )
     * )
     */
    public function verifyEmail(Request $request, $id, $hash)
    {
        $user = User::findOrFail($id);

        if (!hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return response()->json([
                'message' => 'Invalid verification link'
            ], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified'
            ]);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json([
            'message' => 'Email verified successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/auth/forgot-password",
     *     summary="Send password reset link",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password reset link sent",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Password reset link sent")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255|exists:users',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'Password reset link sent'
            ]);
        }

        return response()->json([
            'message' => 'Unable to send reset link'
        ], 400);
    }

    /**
     * @OA\Post(
     *     path="/api/auth/reset-password",
     *     summary="Reset password",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"token","email","password","password_confirmation"},
     *             @OA\Property(property="token", type="string"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="NewStrongPassword123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="NewStrongPassword123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password reset successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Password reset successful")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'email' => 'required|string|email|max:255',
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ]);

                $user->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Password reset successful'
            ]);
        }

        return response()->json([
            'message' => 'Invalid reset token'
        ], 400);
    }

    /**
     * @OA\Post(
     *     path="/api/auth/change-password",
     *     summary="Change password for authenticated user",
     *     tags={"Authentication"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"current_password","password","password_confirmation"},
     *             @OA\Property(property="current_password", type="string", format="password", example="CurrentPassword123"),
     *             @OA\Property(property="password", type="string", format="password", example="NewStrongPassword123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="NewStrongPassword123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password changed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Password changed successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Current password is incorrect"
     *     )
     * )
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect'
            ], 401);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'message' => 'Password changed successfully'
        ]);
    }
}
