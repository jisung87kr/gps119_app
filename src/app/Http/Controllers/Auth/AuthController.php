<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->only(['password']);
        $loginField = $request->input('login_field');
        
        // Determine if login field is email or phone
        if (filter_var($loginField, FILTER_VALIDATE_EMAIL)) {
            // Email login (for admin)
            $credentials['email'] = $loginField;
            $user = User::where('email', $loginField)->first();
        } else {
            // Phone login (for regular users)
            $cleanPhone = preg_replace('/[^0-9]/', '', $loginField);
            $credentials['phone'] = $cleanPhone;
            $user = User::where('phone', $cleanPhone)->first();
        }

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'login_field' => ['로그인 정보가 올바르지 않습니다.'],
            ]);
        }

        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function showAdminRegister()
    {
        return view('auth.admin-register');
    }

    public function adminRegister(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Assign admin role
        $user->assignRole('admin');

        Auth::login($user);

        return redirect()->route('dashboard');
    }
}