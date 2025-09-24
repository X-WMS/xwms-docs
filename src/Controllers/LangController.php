<?php

namespace XWMS\Package\Controllers;

use Illuminate\Support\Facades\App;
use Illuminate\Http\Request;

use XWMS\Package\Controllers\Controller;
use XWMS\Package\Services\IpService;

class LangController extends Controller
{
    public function checkLocale(Request $request)
    {
        return $this->safeExecute(function () use ($request) {
            if (!session('locale')){
                $this->setLocale($request, "", true);
            }
    
            App::setLocale(session('locale'));
        });
    }

    public function setLocale(Request $request, string $locale, bool $isDefault = false)
    {
        return $this->safeExecute(function () use ($request, $locale, $isDefault) {
            $lang = $locale;
    
            if ($isDefault === true) {
                $ipData = IpService::getIpData();
                $lang = strtolower($ipData['countrycode'] ?? config("xwms.locale.default", "en")); // Zet naar kleine letters
            }
        
            if (!in_array($lang, config("xwms.locale.locales", ["en"]))) {
                $lang = config("xwms.locale.default", "en");
            }
    
            App::setLocale($lang);
            session(['locale' => $lang]);
        
            return back();
        });
    }
}