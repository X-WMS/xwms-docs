<?php

namespace XWMS\Package\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

use Illuminate\Support\Facades\RateLimiter;

abstract class Controller
{
    protected string $validateTokenUrl = "/oauth/validate-token";
    protected function handleCrudTableData($key, $validatedData)
    {
        // Filter de validated data om alleen niet-lege waarden over te houden
        $updateData = array_filter($validatedData, function ($value) {
            return !is_null($value) && $value !== '';
        });
    
        // Controleer of er iets is om bij te werken
        if (empty($updateData)) {
            throw new \Exception('Nothing has changed.');
        }
    
        // Verplaats de 'data' naar de specifieke $key in de updateData array
        if (isset($updateData['data']) && is_array($updateData['data'])) {
            $formattedData = [];
            foreach ($updateData['data'] as $item) {
                if (isset($item['name']) && isset($item['value'])) {
                    $formattedData[$item['name']] = $item['value'];
                }
            }
    
            $updateData[$key] = $formattedData; // Voeg de geformatteerde data toe onder $key
            unset($updateData['data']); // Verwijder 'data' uit de hoofdarray
        }
    
        return $updateData;
    }
    
    protected function isSensitiveError(\Throwable $e): bool
    {
        // Zoekwoorden die wijzen op systeemfouten
        $sensitiveKeywords = ['SQL', 'syntax', 'file', 'permission', 'failed'];
    
        // Controleer of de foutmelding een van deze termen bevat
        foreach ($sensitiveKeywords as $keyword) {
            if (stripos($e->getMessage(), $keyword) !== false) {
                return true;
            }
        }
    
        return $e instanceof \PDOException || $e instanceof \ErrorException;
    }
    
    

    protected function safeExecute(callable $callback, $debug = false, array $extras = [])
    {
        // $debug = true;
        
        try {
            return $callback();
        } catch (\Throwable $e) { // 🔥 Vangt nu ALLES
            // return response()->json([
            //     'status' => 'test',
            //     'message' => 'test',
            //     'extras' => $extras
            // ], 400);
    
            // Uiteindelijk als test klaar is:
            return $this->handleException($e, $debug, $extras);
        }
    }

    protected function handleException(\Throwable $e, $debug = false, $extras = [])
    {
        if ($debug === true) {
            return response()->json($e->getMessage(), 400);
        }

        $file = $e->getFile();
        $class = get_class($e);

        // ⛔️ Gevoelige fout tenzij anders bepaald
        $isFromApp = str_starts_with($file, base_path('app'));
        $isFromYourPackage = str_contains($file, base_path('vendor/xwmsshared/core/src'));

        $nonSensitiveExceptions = [
            \Illuminate\Validation\ValidationException::class,
            \Illuminate\Http\Exceptions\PostTooLargeException::class,
            \Illuminate\Auth\AuthenticationException::class,
            \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        ];

        $isNonSensitive =
            in_array($class, $nonSensitiveExceptions) ||
            str_contains($file, 'Illuminate/Support/helpers.php');

        $isSensitive = !$isFromApp && !$isFromYourPackage && !$isNonSensitive;

        $message = $isSensitive
            ? "There was an internal issue. We are working on it. Please try again later.."
            : $e->getMessage();

        $status = $isSensitive ? 'fatal' : 'error';

        if (isset($extras['return'])) {
            return [
                'status' => $status,
                'message' => $message,
                'returnWasOn' => true,
            ];
        }

        if (request()->expectsJson() || isset($extras['json'])) {
            return response()->json([
                'status' => $status,
                'message' => $message,
            ], 400);
        }

        return back()->with($status, $message)->withInput();
    }


    protected $createFunctions = [];
    protected function createHelper(Request $request, string $type, string $successMessage, $service, bool $json = false)
    {
        $xwms_id = $this->getUserId();
        $validatedData = $this->validateRequest($request, $type);
        $function = $this->createFunctions[$type];
        $service->$function($xwms_id, $validatedData);

        if ($json) return response()->json(['status' => 'success', 'message' => $successMessage]);
        return back()->with('success', $successMessage);
    }

    protected function updateHelper(Request $request, string $type, array $validatedData, $service)
    {
        $xwms_id = $this->getUserId();
        $id = $validatedData['id'];
        $updateData = array_filter($validatedData, function ($value, $key) {
            return $key !== 'id' && !is_null($value) && $value !== '';
        }, ARRAY_FILTER_USE_BOTH);
        if (empty($updateData)) {throw new \Exception('Nothing has changed');}

        $response = $service->updateCrudData($xwms_id, $type, $id, $updateData);
        return response()->json($response);
    }

    protected function deleteHelper(Request $request, string $type, $service)
    {
        $xwms_id = $this->getUserId();
        $validatedData = $request->validate(['id' => 'required|integer|min:1']);
        $id = $validatedData['id'];
        $response = $service->deleteCrudData($xwms_id, $type, $id);
        return response()->json($response);
    }

    protected function validateRequest(Request $request, string $type, array $additionalRules = [], $isUpdate = false): array
    {
        $validationRules = match ($type) {
            default => throw new \Exception('Invalid type provided.')
        };
        return $request->validate(array_merge($validationRules, $additionalRules));
    }

    protected function getUserId()
    {
        $xwms_id = Auth::id();
        if (!$xwms_id) throw new \Exception("The system detected you are not logged in. please refresh your page.");
        return $xwms_id;
    }

    protected function getLocationFromGoogle(string $postalcode, string $housenumber): array
    {
        $apiKey = config("xwms.google.MapsApiKey", null); // Zorg dat de API-key in .env staat
        if (!$apiKey) return [
            'streetName' => 'N/A',
            'countryShortName' => 'N/A',
            'postalCode' => 'N/A',
            'streetNumber' => 'N/A',
            'city' => 'N/A',
            'countryLongName' => 'N/A',
            'coordinates' => [
                'lat' => 'N/A',
                'lng' => 'N/A'
            ]
        ];;

        // Maak het adres correct op
        $address = urlencode("$postalcode $housenumber");

        // Geocode API - Haal breedte- en lengtegraad op
        $geoResponse = Http::get("https://maps.googleapis.com/maps/api/geocode/json", [
            'address' => $address,
            'key' => $apiKey,
        ]);

        $geoData = $geoResponse->json();

        // dd($geoData, $postalcode, $housenumber);

        if (empty($geoData['results'][0]['geometry']['location'])) {
            throw new \Exception("Invalid address ::specificerrors{postalcode,housenumber}");
        }

        $location = $geoData['results'][0]['geometry']['location'];
        $latitude = $location['lat'];
        $longitude = $location['lng'];

        // Reverse Geocode API - Haal locatie-informatie op
        $reverseGeoResponse = Http::get("https://maps.googleapis.com/maps/api/geocode/json", [
            'latlng' => "$latitude,$longitude",
            'key' => $apiKey,
        ]);

        $reverseGeoData = $reverseGeoResponse->json();
        if (empty($reverseGeoData['results'][0]['address_components'])) {
            throw new \Exception("Could not determine location details ::specificerrors{postalcode,housenumber}");
        }

        // Extract relevante adrescomponenten
        $addressComponents = $reverseGeoData['results'][0]['address_components'];

        $data = [
            'streetName' => 'N/A',
            'countryShortName' => 'N/A',
            'postalCode' => 'N/A',
            'streetNumber' => $housenumber,
            'city' => 'N/A',
            'countryLongName' => 'N/A',
            'coordinates' => [
                'lat' => $latitude,
                'lng' => $longitude
            ]
        ];

        foreach ($addressComponents as $component) {
            if (in_array('route', $component['types'])) {
                $data['streetName'] = $component['long_name'];
            } elseif (in_array('country', $component['types'])) {
                $data['countryShortName'] = $component['short_name'];
                $data['countryLongName'] = $component['long_name'];
            } elseif (in_array('postal_code', $component['types'])) {
                $data['postalCode'] = $component['long_name'];
            } elseif (in_array('locality', $component['types'])) {
                $data['city'] = $component['long_name'];
            }
        }

        return $data;
    }

    private const PARENT_INPUT_SESSION_KEY = 'savedInputs';

    /**
     * Beheer sessie-opslag voor validatiegegevens.
     *
     * @param string $key
     * @return object
     */
    public function savedInput(string $key)
    {
        return new class($key) {
            private string $sessionKey;

            public function __construct(string $key)
            {
                $this->sessionKey = "savedInputs.{$key}"; // Sla onder een parent key op
            }

            /**
             * Sla de validatiegegevens op in de sessie onder de parent sessie.
             *
             * @param array $data
             */
            public function set(array $data): void
            {
                $filtered = collect($data)->filter(function ($value) {
                    if ($value instanceof \Illuminate\Http\UploadedFile) {
                        return false;
                    }
            
                    if (is_array($value)) {
                        foreach ($value as $item) {
                            if ($item instanceof \Illuminate\Http\UploadedFile) {
                                return false;
                            }
                        }
                    }
            
                    return true;
                })->toArray();
            
                Session::put($this->sessionKey, $filtered);
            }
            /**
             * Haal validatiegegevens op.
             *
             * @param string|null $key (optioneel, voor specifieke sleutel)
             * @return mixed
             */
            public function get(string|null $key = null)
            {
                $data = Session::get($this->sessionKey, []);

                return $key ? ($data[$key] ?? null) : $data;
            }

            /**
             * Update bestaande sessiegegevens.
             *
             * @param string|array $key
             * @param mixed $value (optioneel als $key een array is)
             */
            public function update($key, $value = null): void
            {
                $data = Session::get($this->sessionKey, []);

                if (is_array($key)) {
                    $data = array_merge($data, $key);
                } else {
                    $data[$key] = $value;
                }

                Session::put($this->sessionKey, $data);
            }

            /**
             * Verwijder specifieke sessie.
             */
            public function destroy(): void
            {
                Session::forget($this->sessionKey);
            }
        };
    }


    /**
     * Haal alle opgeslagen sessies op onder de parent sessie.
     *
     * @return array
     */
    public function getAllSavedInputs(): array
    {
        return Session::get(self::PARENT_INPUT_SESSION_KEY, []);
    }

    protected function hsrImg(string $img): string
    {
        return "hsr::img::{$img}";
    }

    protected function hsrTitle(string $title): string
    {
        return "hsr::title::" . str_replace(' ', '_', $title);
    }

    protected function hsrStatus(string $status): string
    {
        return "hsr::status::{$status}";
    }

    protected function hsrDuration(int $ms): string
    {
        return "hsr::duration::{$ms}";
    }

    protected function hsrClosable(bool $bool): string
    {
        return "hsr::closable::" . ($bool ? 'true' : 'false');
    }

    protected function hsrIcon(string $icon): string
    {
        return "hsr::icon::{$icon}";
    }

    // Voeg eventueel meer toe zoals:
    protected function hsrPosition(string $pos): string
    {
        return "hsr::position::{$pos}";
    }
    protected function checkRateLimit(Request $request, string $key, int $cooldownSeconds, array $options = [])
    {
        $ip = $request->ip();
        $sessionId = session()->getId();
        $throttleKey = $key . ':' . sha1($ip . '|' . $sessionId);
    
        $maxFreeAttempts = $options['startAfter'] ?? 0;
        $attempts = RateLimiter::attempts($throttleKey);
    
        if ($attempts >= $maxFreeAttempts) {
            if (RateLimiter::tooManyAttempts($throttleKey, 1)) {
                $msg = $options['message'] ?? 'before trying again.';
                if (!empty($options['removeMsg'])) {
                    throw new \Exception($msg);
                }

                $rawSeconds = RateLimiter::availableIn($throttleKey);
                $secondsRemaining = (int) ceil((float) $rawSeconds); // ✅ gegarandeerd integer
    
                throw new \Exception("Please wait {$secondsRemaining} second" . ($secondsRemaining === 1 ? '' : 's') . " {$msg}");
            }
    
            RateLimiter::hit($throttleKey, $cooldownSeconds);
        } else {
            RateLimiter::hit($throttleKey, 0);
        }
    }
}
