<?php

namespace App\Http\Controllers\SSO;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class SSOController extends Controller
{
    //function getLogin
    public function getLogin(Request $request)
    {
        $request->session()->put("state", $state = Str::random(40));
        $query = http_build_query([
            "client_id" => "9665cac8-b591-454a-8a72-2f6078545ed3",
            "redirect_uri" => "http://127.0.0.1:8080/callback", //calling the sso-auth
            "response_type" => "code",
            "scope" => "view-user",
            "state" => $state
        ]);
        return redirect("http://127.0.0.1:8000/oauth/authorize?" . $query); //return to main app
    }
    //function getCallback
    public function getCallback(Request $request)
    {
        $state = $request->session()->pull("state");

        throw_unless(strlen($state) > 0 && $state == $request->state, InvalidArgumentException::class);

        $response = Http::asForm()->post(
            "http://127.0.0.1:8000/oauth/token",
            [
                "grant_type" => "authorization_code",
                "client_id" => "9665cac8-b591-454a-8a72-2f6078545ed3",
                "client_secret" => "zMU5jD9lCsX7xhYKblrnZu8NHZmrawP1VugfVScI",
                "redirect_uri" => "http://127.0.0.1:8080/callback",
                "code" => $request->code
            ]
        );
        $request->session()->put($response->json());
        return redirect(route("sso.connect"));
    }
    //function connectUser
    public function connectUser(Request $request)
    {
        $access_token = $request->session()->get("access_token");
        $response = Http::withHeaders([
            "Accept" => "application/json",
            "Authorization" => "Bearer " . $access_token
        ])->get("http://127.0.0.1:8000/api/user");
        //fetching/creating users for login into the application
        $userArray= $response->json();
        try {
            $email = $userArray['email'];
        } catch(\Throwable $th) {
            return redirect("login")->withError("Failed to get login information! Try again.");
        }
        $user = User::where("email", $email)->first();
        if (!$user) {
            $user = new User;
            $user->name = $userArray['name'];
            $user->email = $userArray['email'];
            $user->email_verified_at = $userArray['email_verified_at'];
            $user->save();
        }
        Auth::login($user);
        return redirect(route("home"));
    }
}
