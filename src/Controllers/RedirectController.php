<?php

namespace XWMS\Package\Controllers;

use Illuminate\Http\Request;
use XWMS\Package\Controllers\Controller;

// all xwms redirects

class RedirectController extends Controller
{
    public function redirectTo(Request $request, string $target)
    {

        $host = "https://xwms.nl";
        if (env('APP_ENV') === "local"){
            $host = "http://127.0.0.1:8001";
        }
        
        $baseUrls = [
            "sign"       => "$host/sign",
            "login"       => "$host/login",
            "signup"      => "$host/signup",
            "logout"      => "$host/logout",
            "oauth-login" =>  "$host/oauth/login",
    
            "account"     => "$host/account",
    
            "xwms"        => "$host/",
            "about"       => "$host/about",
            "projects"    => "$host/projects",
            "contact"     => "$host/contact",
    
            "easytunez"   => "https://easytunez.com/",
            "mailfi"      => "https://xworkspace.nl/",

            "twitter"     => "https://x.com/xwms_comapny",
            "tiktok"     => "https://www.tiktok.com/@xwms.company",
            "facebook"     => "https://x.com/xwms_comapny",
            "instagram"     => "https://x.com/xwms_comapny",
            "youtube"     => "https://www.youtube.com/@XWMS_COMPANY",
        ];
    
        if (!isset($baseUrls[$target])) {
            abort(404, 'Redirect target not found.');
        }
    
        $url = $baseUrls[$target];
    
        // Basis: bestaande query parameters ophalen
        $query = $request->query();
    
        // 🔥 Speciale behandeling voor oauth-login
        if ($target === 'oauth-login') {
            $query = array_merge([
                'client_id' => env('XWMS_CLIENT_ID'),
                'redirect_uri' => env('XWMS_CLIENT_REDIRECT'),
                'fallback_uri' => env('XWMS_CLIENT_FALLBACK')
            ], $query);
        }
    
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }
    
        return redirect()->away($url);
    }
    
}
