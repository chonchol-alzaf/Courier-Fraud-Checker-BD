<?php
namespace Alzaf\CourierFraudCheckerBd\Services;

use Alzaf\CourierFraudCheckerBd\Supports\CourierFraudCheckerHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CarryBeeService
{
    protected string $cacheKey  = 'carrybee_access_token';
    protected int $cacheMinutes = 50;
    protected string $phone;
    protected string $password;

    public function __construct()
    {
        // Validate config presence
        CourierFraudCheckerHelper::checkRequiredConfig([
            'courier-fraud-checker-bd.carrybee.phone',
            'courier-fraud-checker-bd.carrybee.password',
        ]);

        // Load from config
        $this->phone    = config('courier-fraud-checker-bd.carrybee.phone');
        $this->password = config('courier-fraud-checker-bd.carrybee.password');

        CourierFraudCheckerHelper::validatePhoneNumber($this->phone);
    }

    protected function getAccessToken()
    {
        // Use cached token if available
        $token = Cache::get($this->cacheKey);
        if ($token) {
            return $token;
        }

        $csrf = Http::get("https://merchant.carrybee.com/api/auth/csrf");

        $csrf_token = $csrf->json("csrfToken");

        if (! $csrf_token) {
            return ['error' => 'CSRF token not found'];
        }

        // $login_response = Http::withHeaders([
        //     "Content-Type"=> "application/x-www-form-urlencoded",
        // ])
        // ->post('https://merchant.carrybee.com/api/auth/callback/login', [
        //     'phone'       => '+88' . $this->phone,
        //     'password'    => $this->password,
        //     'csrfToken'   => $csrf_token,
        //     'callbackUrl' => 'https://merchant.carrybee.com/login'
        // ]);

        // $setCookies = $login_response->header('Set-Cookie');

        $login_response = Http::withHeaders([
            'Accept'             => '*/*',
            'Accept-Encoding'    => 'gzip, deflate, br, zstd',
            'Accept-Language'    => 'en-US,en;q=0.5',
            'Content-Type'       => 'application/x-www-form-urlencoded',
            'Origin'             => 'https://merchant.carrybee.com',
            'Referer'            => 'https://merchant.carrybee.com/login',
            'User-Agent'         => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36',
            'Sec-Ch-Ua'          => '"Not:A-Brand";v="99", "Brave";v="145", "Chromium";v="145"',
            'Sec-Ch-Ua-Mobile'   => '?0',
            'Sec-Ch-Ua-Platform' => '"Linux"',
            'Sec-Fetch-Dest'     => 'empty',
            'Sec-Fetch-Mode'     => 'cors',
            'Sec-Fetch-Site'     => 'same-origin',
            'Cookie'             => "___Host-authjs.csrf-token=79e970f49cb4fb894aff696eaafe853df177bbe40d4186263a5a2642460551f2%7Cb2153987af0949a20c85c3653a02d1900516c04aa0d0007f7a166cfbff7f4131; __Secure-authjs.callback-url=https%3A%2F%2Fmerchant.carrybee.com%2Flogin; locale=en",

        ])->asForm()->post(
            'https://merchant.carrybee.com/api/auth/callback/login?redirect=false',
            [
                'phone'     => '+88' . $this->phone,
                'password'  => $this->password,
                'csrfToken' => $csrf_token,
            ]
        );

        $setCookies = $login_response->header('Set-Cookie');

        // return $login_response;
        return $setCookies;

        $rawCookies   = $login_response->cookies();
        $cookiesArray = [];
        foreach ($rawCookies->toArray() as $cookie) {
            $cookiesArray[$cookie['Name']] = $cookie['Value'];
        }

        return $cookiesArray;

        $response = Http::withCookies($cookiesArray, 'merchant.carrybee.com')->get("https://merchant.carrybee.com/api/auth/session");

        return $response;

        if (! $login_response->successful()) {
            return ['error' => 'Login failed'];
        }

        Log::debug("success");

        return $login_response->body();
        return $login_response->json();
    }

    public function getCustomerDeliveryStats(string $queryPhone)
    {
        CourierFraudCheckerHelper::validatePhoneNumber($queryPhone);

        return $accessToken = $this->getAccessToken();

        $login_cookie = "__Secure-authjs.session-token=eyJhbGciOiJkaXIiLCJlbmMiOiJBMjU2Q0JDLUhTNTEyIiwia2lkIjoiRHZzSzY2SGFwcEFFR0p4ak9NOXYyNXZkLVJLTTQ2NnJ1MkZvbjEtQ3B0ZDVnZmkzVU51VGZqZkFJZUMyU3cxQ1lHbDZOamZ4Q3ZWY25IWDVySFI5X0EifQ..16csKbg1PUvRA7J5urNfaw.qmOH3JmAxh1xmJ9N5Je_eeP_OncNoi2drcQLyuWxYHQhwvGtVJhr4KUfhYmHQkjIOkGK1-yfPUPhavbvFnU3rXA6tLiEa9FyWMOnlZhskrCXNzK4ZJwxOSNL4OgbRiNGaruljI8wAQnLV-1nsjzezCrDMs4OW3TKiDLcLM6TAk2AcjPkHqMIqUTk07RPfODf0j4U2srnikC_C_DJGtF6AgrGN_cvvijCYUG9KmMkditUxLnE68kzgaVgO65l_Tu7u1280eo5V2_Zrr4KBbVXV72Bej2A8XcmzD3iejiO379NQvOJuwU9w9xYvvyj3Dw2b3zDVGtFRs2S2PBbtGT1ook-Be1343OTqLf3-r1ASlKHtOPAA-X5TgBpkFmL4sN_oGyKE_1HPeTCziuZvipxkuZ3qoMRlBa0Xj6LtiYE28l9JgjBey6xUevr_1YndQTMw0zWho1yQzsRdj80AN3n28E12LGGyasQTZ6Axv7k48ud_3sNQSjY1C_G8VGzm2FrMSrIstcXzWpx2YWMkoDtAbp3rBjB7_JZokD-xLLZDZwAk-7cQsWkapYuVP_r36ChPdCCK69yM4-LEsFZ0sriQSjUjW2ArVO5T_vdMAIbDxSbBymMsF_DJaEXTZeKgdFmtDkp-Vt4myULWs8z61jls7LA_1bXYNw0zyZxQeHg_WW_JyMaiW58sd6vGrUkiHFPYbZ1FBDjxjQBPEH6dKXHjjC8sx-q38_1r-nSNhKrcRYq8DJSN0t_tif-bX9GirZ0dYmcABHtIDiSj_bRYMvqkDr6U4gH85ytDENvHpc0-Gt7OozZnjD5_MKOtoshl7tDR-cfY2kzu2T1x5ral9pa5iulTZw_iJAi0Ho_rmdD7OcOjlOoBrKkXQJFT_-gTxT5auB89HJukyDsdx8TJpSZlqMgs7MCPvLWqTQ-KVAYkixDQnevPvRz7VWu3Or-iphf0cXwjhApcj7J8dUpYb8lDAcBbd-XkVI9s0ovz59ZTWCTRjhCyWBzNi2W0vu93AwynTfwy1dJ2_cSpWFVR_IsW5Wqly4pmBR7sVzF9wvk8dbJWHbHB0YTlexj7h17Y_Jrs1TzfcIA3RJ6PjxfTyld2J7opglbBOEv4UHlGhRrP7j8gn-GlHxO5qZmbeiEydqlJzNUpvACpKeN_oiJKkfGbh9oPewMIBCgzfnQcmJxI2sRtKPlBM68DwqzxaEOTgQZXnyCkmcmstYXJTkbQJrB7dzSty6xZx2lEgzDbam2DV4._Liqe68LJOYK2L58WCcmQJ1Sq8TsRqgkGlke1Y8YZfE; Path=/; Expires=Sat, 04 Apr 2026 09:33:02 GMT; HttpOnly; Secure; SameSite=Lax";

        $response = Http::withCookie($login_cookie)->get("https://merchant.carrybee.com/api/auth/session");

        $accessToken = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhdWQiOiJhcGktbWVyY2hhbnQuY2FycnliZWUuY29tIiwiZXhwIjoxNzgwNDQ4NDAwLCJleHRlcm5hbF9pZCI6MTUzMTAsImlhdCI6MTc3MjcwMTUwNiwiaWQiOiI2OWE5MGE3M2ExNjc4MDQ1NmY4Y2ZjYjkiLCJuYmYiOjE3NzI3MDE1MDYsInVzZXJfdHlwZSI6Im1lcmNoYW50IiwidXVpZCI6IjYwODM2MzQ2LWFmZDctNDczZC1hNzcyLWZjZDMzNjlkNDU1NyJ9.43x_zDHuAHWOpAwKUTNnSIipSkQI4oiO1lUIe9o1xu4";

        // $response = Http::withHeaders([
        //     'User-Agent'    => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        //     'Accept'        => 'application/json, text/plain, */*',
        //     'Content-Type'  => 'application/json',
        //     'Authorization' => 'Bearer ' . $accessToken,
        // ])->get("https://api-merchant.carrybee.com/api/v2/businesses/15069/customers/+88{$queryPhone}");

        $response = Http::withHeaders([
            'User-Agent'    => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'Accept'        => 'application/json, text/plain, */*',
            'Content-Type'  => 'application/json',
            'Client-ID'     => 'aa7b9183-0c37-4fe5-a9d5-eccc361e89ea',
            'Client-Secret' => '6b1f0113-18c5-45b6-87f3-c77020092307',
            'Client-Contex' => 'E9fwhxLZ9ck8reBm0UyPW6j1V2vblN',
        ])->get("https://developers.carrybee.com/api/v2/cities");

        $message = $response->json();

        return $message;
    }
}
