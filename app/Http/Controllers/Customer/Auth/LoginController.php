<?php

namespace App\Http\Controllers\Customer\Auth;

use App\CPU\CartManager;
use App\CPU\Helpers;
use App\Http\Controllers\Controller;
use App\Model\BusinessSetting;
use App\Model\Wishlist;
use App\User;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Gregwar\Captcha\CaptchaBuilder;
use Illuminate\Support\Facades\Session;

class LoginController extends Controller
{
    public $company_name;

    public function __construct()
    {
        $this->middleware('guest:customer', ['except' => ['logout']]);
    }
    

    public function login()
    {
        // session()->put('keep_return_url', url()->previous());
        // $custome_recaptcha = new CaptchaBuilder;
        // $custome_recaptcha->build();
        // Session::put('custome_recaptcha', $custome_recaptcha->getPhrase());
        return view('customer-view.auth.login');
    }

    public function submit(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'password' => 'required|min:8'
        ]);
       
        $remember = ($request['remember']) ? true : false;

        $user = User::where(['phone' => $request->user_id])->orWhere(['email' => $request->user_id])->first();

        
        if (isset($user) == false) {
            Toastr::error('Credentials do not match or account has been suspended.');
            return back()->withInput();
        }

        $phone_verification = Helpers::get_business_settings('phone_verification');
        $email_verification = Helpers::get_business_settings('email_verification');
        if ($phone_verification && !$user->is_phone_verified) {
            return redirect(route('customer.auth.check', [$user->id]));
        }
        if ($email_verification && !$user->is_email_verified) {
            return redirect(route('customer.auth.check', [$user->id]));
        }

        if (isset($user) && $user->is_active && auth('customer')->attempt(['email' => $user->email, 'password' => $request->password], $remember)) {
            session()->put('wish_list', Wishlist::where('customer_id', auth('customer')->user()->id)->pluck('product_id')->toArray());
            Toastr::info('Welcome to ' . Helpers::get_business_settings('company_name') . '!');
            CartManager::cart_to_db();
            // return redirect(session('keep_return_url'));
            return redirect()->route('home');
        }

        Toastr::error('Credentials do not match or account has been suspended.');
        return back()->withInput();
    }

    public function logout(Request $request)
    {
        auth()->guard('customer')->logout();
        session()->forget('wish_list');
        Toastr::info('Come back soon, ' . '!');
        return redirect()->route('home');
    }
}
