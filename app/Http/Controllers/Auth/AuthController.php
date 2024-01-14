<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserLogin;
use Illuminate\Support\Facades\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

use Illuminate\Foundation\Auth\RedirectsUsers;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\PasswordReset;
use App\Providers\RouteServiceProvider;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // $fields = $request->validate([
        //     'name' => 'required|string',
        //     'email' => 'required|string|email|unique:users,email',
        //     'password' => 'required|string|confirmed'
        // ]);

        $fields = $request->all(); // shortcut due to long validation list

        $user = User::create([
            'username' => $fields['username'],
            'email' => $fields['email'],
            'password' => bcrypt($fields['password']),
            // 'auth_level_id' => $fields['auth_level_id'],
            // 'designation' => $fields['designation'],
            // 'emp_number' => $fields['emp_number'],
            // 'department' => $fields['department'],
            // 'unsettled_limit' => $fields['unsettled_limit'],
            // 'created_by' => $fields['created_by'],
            // 'status' => 'pending',
        ]);

        $token = $user->createToken('myapptoken')->plainTextToken;

        $response = [
            'user' => $user,
            'token' => $token
        ];

        return response($response, 201);
    }

    public function login(Request $request)
    {
        // $request->validate([
        //     'email' => 'required|string|email',
        //     'password' => 'required|string'
        // ]);

        // //check email
        // $user = User::where('email', $request->email)->first();

        // // Check password
        // if(!$user || !Hash::check($request->password, $user->password)) {
        //     return response([
        //         'message' => 'Bad creds'
        //     ], 401);
        // }

        // $token = $user->createToken('myapptoken')->plainTextToken;

        // $response = [
        //     'user' => $user,
        //     'token' => $token
        // ];

        // return response($response, 201);



        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);


        if (Auth::guard('web')->attempt($credentials)) {

            // Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerate();
            $request->user()->tokens()->delete();

            $request->session()->regenerate();
            //check email
            $user = User::where('email', $request->email)->first();

            // if ($user->status != 'active') {
            //     $request->session()->invalidate();
            //     $request->session()->regenerate();
            //     $request->user()->tokens()->delete();

            //     return response([
            //         'message' => 'You are not active to perform any action. Please contact your supervisor'
            //     ], 401);
            // }

            // return $user->userLogins;
            // $user_detail = User::find($user->id);
            // $user_detail->userLogins()->attach($user_detail);

            $latestLogins = UserLogin::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();

            // return $latestLogins->count();

            if ($latestLogins->count() > 0) {
                // Keep only the two latest records
                $latestLoginsToKeep = $latestLogins->take(2);

                // Delete the older records
                UserLogin::where('user_id', $user->id)
                    ->where('id', '!=', $latestLoginsToKeep->pluck('id'))
                    ->delete();
            }


            $userLogin = new UserLogin();
            $userLogin->user_id = $user->id;
            $userLogin->save();

            $token = $user->createToken('myapptoken')->plainTextToken;

            // $user->session_ids = $token;

            $user->save();

            // $token = $user->createToken('myapptoken')->plainTextToken;

            // return redirect()->intended('dashboard');
            return response([
                'message' => 'Login successful',
                'token' => $token,
                'user_id' => $user->id,
            ], 201);
        }

        return response([
            'message' => 'Login failed'
        ], 401);
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

    //-----------------------------------------------------------FORGOT PASSWORD----------------------------------




    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    // use SendsPasswordResetEmails;
    /**
     * Display the form to request a password reset link.
     *
     * @return \Illuminate\View\View
     */
    // public function showLinkRequestForm()
    // {
    //     if (Auth::guard('cashiers')->check()) {
    //         return redirect()->intended($this->redirectPath());
    //     }
    //     return view('cashier-views.auth.passwords.email');
    // }

    /**
     * Send a reset link to the given user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function sendResetLinkEmail(Request $request)
    {

        $this->validateEmail($request);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $response = $this->broker()->sendResetLink(
            $this->credentials($request)
        );

        return $response == Password::RESET_LINK_SENT
            ? $this->sendResetLinkResponse($request, $response)
            : $this->sendResetLinkFailedResponse($request, $response);
    }

    /**
     * Validate the email for the given request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function validateEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);
    }

    /**
     * Get the needed authentication credentials from the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    protected function credentials(Request $request)
    {
        return $request->only('email');
    }

    /**
     * Get the response for a successful password reset link.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $response
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    protected function sendResetLinkResponse(Request $request, $response)
    {
        return $request->wantsJson()
            ? new JsonResponse(['message' => trans($response)], 200)
            : back()->with('status', trans($response));
    }

    /**
     * Get the response for a failed password reset link.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $response
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function sendResetLinkFailedResponse(Request $request, $response)
    {
        if ($request->wantsJson()) {
            throw ValidationException::withMessages([
                'email' => [trans($response)],
            ]);
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => trans($response)]);
    }

    /**
     * Get the broker to be used during password reset.
     *
     * @return \Illuminate\Contracts\Auth\PasswordBroker
     */
    public function broker()
    {
        return Password::broker('users');
    }



    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
    */

    // use ResetsPasswords;

    /**
     * Where to redirect users after resetting their password.
     *
     * @var string
     */
    protected $redirectTo = '/password/reset-success';

    use RedirectsUsers;

    /**
     * Display the password reset view for the given token.
     *
     * If no token is present, display the link request form.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function showResetForm(Request $request)
    {
        $token = $request->route()->parameter('token');

        return view('api-support-views.auth.passwords.reset')->with(
            ['token' => $token, 'email' => $request->email]
        );
    }

    /**
     * Reset the given user's password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function reset(Request $request)
    {
        $request->validate($this->rules(), $this->validationErrorMessages());

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $response = $this->broker()->reset(
            $this->passwordResetCredentials($request),
            function ($user, $password) {
                $this->resetPassword($user, $password);
            }
        );

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        return $response == Password::PASSWORD_RESET
            ? $this->sendResetResponse($request, $response)
            : $this->sendResetFailedResponse($request, $response);
    }

    /**
     * Get the password reset validation rules.
     *
     * @return array
     */
    protected function rules()
    {
        return [
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ];
    }

    /**
     * Get the password reset validation error messages.
     *
     * @return array
     */
    protected function validationErrorMessages()
    {
        return [];
    }

    /**
     * Get the password reset credentials from the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    protected function passwordResetCredentials(Request $request)
    {
        return $request->only(
            'email',
            'password',
            'password_confirmation',
            'token'
        );
    }

    /**
     * Reset the given user's password.
     *
     * @param  \Illuminate\Contracts\Auth\CanResetPassword  $user
     * @param  string  $password
     * @return void
     */
    protected function resetPassword($user, $password)
    {
        $this->setUserPassword($user, $password);

        $user->setRememberToken(Str::random(60));

        $user->save();

        event(new PasswordReset($user));

        // $this->guard()->login($user);
    }

    /**
     * Set the user's password.
     *
     * @param  \Illuminate\Contracts\Auth\CanResetPassword  $user
     * @param  string  $password
     * @return void
     */
    protected function setUserPassword($user, $password)
    {
        $user->password = Hash::make($password);
    }

    /**
     * Get the response for a successful password reset.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $response
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    protected function sendResetResponse(Request $request, $response)
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['message' => trans($response)], 200);
        }

        return redirect($this->redirectPath())
            ->with('status', trans($response));
    }

    /**
     * Get the response for a failed password reset.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $response
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    protected function sendResetFailedResponse(Request $request, $response)
    {
        if ($request->wantsJson()) {
            throw ValidationException::withMessages([
                'email' => [trans($response)],
            ]);
        }

        return redirect()->back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => trans($response)]);
    }


    /**
     * Get the guard to be used during password reset.
     *
     * @return \Illuminate\Contracts\Auth\StatefulGuard
     */
    protected function guard()
    {
        return Auth::guard('web');
    }


    //==================Change Password=======================

    public function changePassword(Request $request)
    {
        $request->validate([
            'currrent_password' => 'required|string',
            'new_password' => ['required', 'string', 'confirmed', Rules\Password::defaults()]
        ]);

        // if (Auth::guard('web')->attempt([$request->user()->email, $request->currrent_password])) {
        if (Hash::check($request->currrent_password, $request->user()->password)) {
            $request->user()->update(['password' => bcrypt($request->new_password)]);

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerate();
            $request->user()->tokens()->delete();

            return response([
                'message' => 'Password updated successfully.'
            ], 200);
        }

        return response([
            'message' => 'Password update failed.'
        ], 401);
    }
}
