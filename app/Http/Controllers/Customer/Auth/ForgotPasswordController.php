<?php

namespace App\Http\Controllers\Customer\Auth;

namespace App\Http\Controllers\Customer\Auth;

use App\CPU\Helpers;
use App\CPU\SMS_module;
use App\Http\Controllers\Controller;
use App\User;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use function App\CPU\translate;

class ForgotPasswordController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest:customer', ['except' => ['logout']]);
    }

    public function reset_password()
    {
        return view('customer-view.auth.recover-password');
    }

    public function reset_password_request(Request $request){

        {
            $request->validate([
                'identity' => 'required',
            ]);
    
            session()->put('forgot_password_identity', $request['identity']);
            $verification_by = Helpers::get_business_settings('forgot_password_verification');
    
            DB::table('password_resets')->where('identity', 'like', "%{$request['identity']}%")->delete();
    
            if ($verification_by == 'email') {
                $customer = User::Where(['email' => $request['identity']])->first();
                if (isset($customer)) {
                    $token = Str::random(120);
                    DB::table('password_resets')->insert([
                        'identity' => $customer['email'],
                        'token' => $token,
                        'created_at' => now(),
                    ]);
                    $reset_url = url('/') . '/customer/auth/reset-password?token=' . $token;
                    Mail::to($customer['email'])->send(new \App\Mail\PasswordResetMail($reset_url));
                    Toastr::success('Check your email. Password reset url sent.');
                    return back();
                }
            } elseif ($verification_by == 'phone') {
                $customer = User::where('phone', 'like', "%{$request['identity']}%")->first();
                if (isset($customer)) {
                    $token = rand(1000, 9999);
                    DB::table('password_resets')->insert([
                        'identity' => $customer['phone'],
                        'token' => $token,
                        'created_at' => now(),
                    ]);
                   
                    $message = "Dear Customer your password reset OTP : ". $token ."Nillgiri.";
    
                    $data = [
                        "api_key" => "R2000263639ebd62451268.51442360",
                        "type" => "text",
                        "contacts" => $customer->phone,
                        "senderid" => "8809601004769",
                        "msg" => $message,
                    ];
                    
                    Http::get('http://isms.mimsms.com/smsapi', $data );
                   
                    // $this->curlSmsSend($customer->phone,$message);
                    
                    Toastr::success('Check your phone. Password reset otp sent.');
                    return redirect()->route('customer.auth.otp-verification');
                }
            }
    
            Toastr::error('No such user found!');
            return back();
        }
    }

    // public function reset_password_request(Request $request)
    // {
    //     $request->validate([
    //         'identity' => 'required',
    //     ]);

    //     session()->put('forgot_password_identity', $request['identity']);
    //     $verification_by = Helpers::get_business_settings('forgot_password_verification');

    //     DB::table('password_resets')->where('identity', 'like', "%{$request['identity']}%")->delete();

    //     if ($verification_by == 'email') {
    //         $customer = User::Where(['email' => $request['identity']])->first();
    //         if (isset($customer)) {
    //             $token = Str::random(120);
    //             DB::table('password_resets')->insert([
    //                 'identity' => $customer['email'],
    //                 'token' => $token,
    //                 'created_at' => now(),
    //             ]);
    //             $reset_url = url('/') . '/customer/auth/reset-password?token=' . $token;
    //             Mail::to($customer['email'])->send(new \App\Mail\PasswordResetMail($reset_url));
    //             Toastr::success('Check your email. Password reset url sent.');
    //             return back();
    //         }
    //     } elseif ($verification_by == 'phone') {
    //         $customer = User::where('phone', 'like', "%{$request['identity']}%")->first();
    //         if (isset($customer)) {
    //             $token = rand(1000, 9999);
    //             DB::table('password_resets')->insert([
    //                 'identity' => $customer['phone'],
    //                 'token' => $token,
    //                 'created_at' => now(),
    //             ]);
    //             SMS_module::send($customer->phone, $token);
    //             Toastr::success('Check your phone. Password reset otp sent.');
    //             return redirect()->route('customer.auth.otp-verification');
    //         }
    //     }

    //     Toastr::error('No such user found!');
    //     return back();
    // }

    public function otp_verification()
    {
        return view('customer-view.auth.verify-otp');
    }

    public function otp_verification_submit(Request $request)
    {
        $id = session('forgot_password_identity');
        $data = DB::table('password_resets')->where(['token' => $request['otp']])
            ->where('identity', 'like', "%{$id}%")
            ->first();
        if (isset($data)) {
            $token = $request['otp'];
            return redirect()->route('customer.auth.reset-password', ['token' => $token]);
        }

        Toastr::error(translate('invalid_otp'));
        return back();
    }

    

    public function reset_password_index(Request $request)
    {
        $data = DB::table('password_resets')->where(['token' => $request['token']])->first();
        if (isset($data)) {
            $token = $request['token'];
            return view('customer-view.auth.reset-password', compact('token'));
        }
        Toastr::error('Invalid credentials');
        return back();
    }

    public function reset_password_submit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|same:confirm_password',
        ]);

        $token = $request['reset_token'];
        if ($validator->fails()) {
            Toastr::error(translate('password_mismatch'));
            return view('customer-view.auth.reset-password', compact('token'));
        }

        $id = session('forgot_password_identity');
        $data = DB::table('password_resets')
            ->where('identity', 'like', "%{$id}%")
            ->where(['token' => $request['reset_token']])->first();

        if (isset($data)) {
            User::where('email', 'like', "%{$data->identity}%")
                ->orWhere('phone', 'like', "%{$data->identity}%")
                ->update([
                    'password' => bcrypt(str_replace(' ', '', $request['password']))
                ]);
            Toastr::success('Password reset successfully.');
            DB::table('password_resets')->where(['token' => $request['reset_token']])->delete();
            return redirect('/');
        }
        Toastr::error('Invalid data.');
        return back();
    }

    private function curlSmsSend($mobile_no, $message)
    {

      $message = urlencode($message);
        $url = "http://66.45.237.70/api.php?username=01818737845&password=BlackAzad&number=$mobile_no&message=$message";
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Sample cURL Request');
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }
}
