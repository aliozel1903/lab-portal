<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // Giriş işlemini kontrol eden fonksiyon
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Auth::attempt() kullanmıyoruz: o yöntem ayrıca bir oturum (session)
        // açar ve API'nin tamamen token tabanlı kalmasını bozar.
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'E-posta veya şifre hatalı!'], 401);
        }

        // Her girişte yeni token üretiliyor; eskilerini iptal ederek
        // unutulmuş oturumların açık kalmasını engelliyoruz.
        $user->tokens()->delete();

        $token = $user->createToken('lab-token')->plainTextToken;

        return response()->json([
            'message' => 'Giriş başarılı',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ], 200);
    }

    // Çıkış: token'ı sunucu tarafında da geçersiz kılar
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Oturum kapatıldı.'], 200);
    }
}
