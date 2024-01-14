<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function userDetails(Request $request)
    {

        $user = User::find($request->user()->id);

        return $user;
    }

    public function logout(Request $request)
    {

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerate();
        $request->user()->tokens()->delete();

        // return redirect()->intended('dashboard');
        return response([
            'message' => 'Logout successful'
        ], 201);
    }
}
