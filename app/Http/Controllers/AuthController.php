<?php

namespace App\Http\Controllers;

use App\Mail\APIResetPassword;
use App\Models\Pengguna;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class AuthController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('auth:api', except: ['login', 'register', 'sendResetEmail', 'reset']),
        ];
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'username' => 'required|string|unique:pengguna',
            'email' => 'required|string|email|unique:pengguna',
            'role' => 'required|in:user,admin',
            'password' => 'required|string|min:8'
        ]);

        return Pengguna::create([
            'name' => $request['name'],
            'username' => $request['username'],
            'email' => $request['email'],
            'role' => $request['role'],
            'password' => Hash::make($request['password']),
        ]);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login()
    {
        $credentials = request(['username', 'password']);

        $pengguna =  Pengguna::select('role')->find(request('username'));

        // An unknown username used to end in a 500 here; it now gets the same 401 as a wrong password.
        if (! $pengguna || ! $token = auth()->guard('api')->claims(['role' => $pengguna->role])->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $this->respondWithToken($token);
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        return response()->json(auth()->user());
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth()->guard('api')->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh());
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60
        ]);
    }

    public function sendResetEmail(Request $request)
    {
        $validator = $request->validate([
            'email'  => 'required|email'
        ]);

        $acc = Pengguna::select('name')
                ->where('email', $request->email)
                ->first();

        if($acc != null) {
            $token = Str::random(60);
            $now = new DateTime();

            DB::table('password_resets')->updateOrInsert(
                ['email' => $request->email],
                ['token' => $token, 'created_at' => $now->format('Y-m-d H:i:s')]
            );

            $reset = (object) array();

            //Generate, the password reset link. The token generated is embedded in the link
            $reset->link = config('app.fe_url') . "/reset/$token";
            $reset->appName = config('app.name');
            $reset->appURL = config('app.fe_url');

            // Mail::failures() was removed in Laravel 9: a failed send now throws.
            try {
                Mail::to($request->email)->send(new APIResetPassword($reset));
            } catch (TransportExceptionInterface $e) {
                report($e);

                return response()->json(['status' => false, 'message' => 'Failed to send reset link email.']);
            }

            return response()->json(['status' => true, 'message' => 'Please check you email for reset link.']);
        } else {
            return response()->json(['status' => false, 'message' => 'User not found or email not matched with login ID.']);
        }
    }

    public function reset(Request $request)
    {
        $validator = $request->validate([
            'token'  => 'required|min:1',
            'password' => 'required|min:6'
        ]);

        $now = new DateTime();

        $token = DB::table('password_resets')->where('token', $request->token)->first();

        if($token != null) {
            $T_created = date_format(date_create($token->created_at),"U");
            $T_now = date_format($now,"U");

            if(($T_now - $T_created) < (60 * 60)) {
                $updatePword = Pengguna::where('email', $token->email)->first();

                if($updatePword != null) {
                    $updatePword->password = Hash::make($request->password);

                    $isSaved = $updatePword->save();

                    if($isSaved) {
                        DB::table('password_resets')->where('token', $request->token)->delete();

                        return response()->json(['status' => true, 'message' => 'New password saved successfully.']);
                    } else {
                        return response()->json(['status' => false, 'message' => 'New password failed to be saved.']);
                    }
                } else {
                    return response()->json(['status' => false, 'message' => 'User not found.']);
                }
            } else {
                return response()->json(['status' => false, 'message' => 'Reset password link has expired.']);
            }
        } else {
            return response()->json(['status' => false, 'message' => 'Reset password link is invalid.']);
        }
    }
}
