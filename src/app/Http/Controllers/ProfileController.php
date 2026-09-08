<?php

namespace App\Http\Controllers;

use App\Services\AccountDeletionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rules;

class ProfileController extends Controller
{
    public function show()
    {
        return view('profile.show');
    }

    public function edit()
    {
        return view('profile.edit');
    }

    public function update(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone,'.Auth::id()],
        ]);

        $user = Auth::user();
        $user->update([
            'name' => $request->name,
            'phone' => $request->phone,
        ]);

        return Redirect::route('profile.show')->with('status', '프로필이 성공적으로 업데이트되었습니다.');
    }

    public function editPassword()
    {
        return view('profile.edit-password');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = Auth::user();
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return Redirect::route('profile.show')->with('status', '비밀번호가 성공적으로 변경되었습니다.');
    }

    public function deleteAccount()
    {
        return view('profile.delete-account');
    }

    /**
     * 회원 탈퇴 — 물리 삭제가 아니라 익명화다 (ADR-0010, `AccountDeletionService`).
     * 지령 이력이 있는 계정은 FK 때문에 삭제가 500 으로 죽었고, 행사 기록도 같이 사라졌다.
     */
    public function destroyAccount(Request $request, AccountDeletionService $deletion)
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = Auth::user();

        Auth::logout();

        $deletion->delete($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/')->with('status', '계정이 성공적으로 삭제되었습니다.');
    }
}
