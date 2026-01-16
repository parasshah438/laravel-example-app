<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Models\UserActivityLog;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    /**
     * The user has been authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $user
     * @return mixed
     */
    protected function authenticated(Request $request, $user)
    {
        // Log the login activity
        UserActivityLog::logLogin($user, $request, [
            'login_method' => 'web',
            'remember_me' => $request->has('remember')
        ]);
        
        // Clear any existing session data for this user
        Cache::forget("user_activity_{$user->id}");
        
        // Set new session data
        Cache::put("user_activity_{$user->id}", [
            'last_activity' => Carbon::now(),
            'session_id' => session()->getId(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'login_time' => Carbon::now()
        ], now()->addMinutes(config('session.lifetime')));
        
        // Check for logout reason in query parameters
        $reason = $request->get('reason');
        if ($reason) {
            $messages = [
                'timeout' => 'Your previous session expired due to inactivity.',
                'browser_close' => 'Your session was ended when the browser was closed.',
                'concurrent' => 'You were logged out because another session was started.',
            ];
            
            if (isset($messages[$reason])) {
                session()->flash('info', $messages[$reason]);
            }
        }
        
        return redirect()->intended($this->redirectPath());
    }

    /**
     * Show the application's login form.
     *
     * @return \Illuminate\View\View
     */
    public function showLoginForm(Request $request)
    {
        $reason = $request->get('reason');
        $message = null;
        
        if ($reason) {
            $messages = [
                'timeout' => 'Your session expired due to inactivity. Please log in again.',
                'browser_close' => 'Your session was ended when the browser was closed. Please log in again.',
                'concurrent' => 'Another session was started with your account. Please log in again.',
                'forced' => 'You have been logged out for security reasons.',
            ];
            
            if (isset($messages[$reason])) {
                $message = $messages[$reason];
            }
        }
        
        return view('auth.login', compact('message'));
    }

    /**
     * Log the user out of the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        $user = Auth::user();
        
        if ($user) {
            // Log the logout activity
            UserActivityLog::logLogout($user, $request, 'manual_logout', [
                'logout_method' => 'web',
                'session_duration_minutes' => $this->getSessionDuration($user->id)
            ]);
            
            // Clear user activity cache
            Cache::forget("user_activity_{$user->id}");
        }

        $this->guard()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($response = $this->loggedOut($request)) {
            return $response;
        }

        return $request->wantsJson()
            ? new \Illuminate\Http\JsonResponse([], 204)
            : redirect('/');
    }
    
    /**
     * Calculate session duration in minutes
     */
    private function getSessionDuration($userId)
    {
        $activity = Cache::get("user_activity_{$userId}");
        
        if ($activity && isset($activity['login_time'])) {
            $loginTime = Carbon::parse($activity['login_time']);
            return $loginTime->diffInMinutes(now());
        }
        
        return null;
    }
}
