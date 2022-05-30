<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function(Request $request) {
    return view('welcome');
});
//add routing for SSO
Route::get("/login", function(Request $request){
    $request->session()->put("state", $state = Str::random(40));
    $query = http_build_query([
        "client_id" => "9665cac8-b591-454a-8a72-2f6078545ed3",
        "redirect_uri" => "http://127.0.0.1:8080/callback", //calling the sso-auth
        "response_type" => "code",
        "scope" => "",
        "state" => $state
    ]);
    return redirect("http://127.0.0.1:8000/oauth/authorize?" . $query); //return to main app
});

//add routing for go back to main app
Route::get("/callback", function(Request $request) {
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
    ]);
    $request->session()->put($response->json());
    return redirect("/authuser");
});

//fetch data oauth2 from sso server, pass the access token in header
Route::get("/authuser", function(Request $request) {
    $access_token = $request->session()->get("access_token");
    $response = Http::withHeaders([
        "Accept" => "application/json",
        "Authorization" => "Bearer " . $access_token
    ])->get("http://127.0.0.1:8000/api/user");
    return $response->json();
});
