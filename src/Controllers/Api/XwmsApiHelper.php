<?php

namespace XWMS\Package\Controllers\Api;

use Exception;
use Throwable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class XwmsApiHelper
{
    /**
     * Usage patterns (examples)
     *
     * 1) Simple controller login (Laravel)
     * --------------------------------------------------
     * Route::get('/xwms/auth', [XwmsApiHelper::class, 'auth']);
     * Route::get('/xwms/validateToken', [HomeController::class, 'authValidate']);
     *
     * public function authValidate()
     * {
     *     $result = XwmsApiHelper::authenticateAndSyncUser();
     *     if (($result['status'] ?? null) !== 'success') {
     *         return redirect()->route('login');
     *     }
     *     return redirect()->route('dashboard');
     * }
     *
     * 2) Custom user model + connection model
     * --------------------------------------------------
     * XwmsApiHelper::authenticateAndSyncUser([
     *     'create_user' => true,
     *     'update_existing' => true,
     *     'link_by_email' => true,
     *     'login' => true,
     *     'remember' => true,
     *     'field_map' => [
     *         'name' => 'name',
     *         'email' => 'email',
     *         'img' => 'picture',
     *     ],
     * ]);
     *
     * 3) Disable auto login (manual session)
     * --------------------------------------------------
     * $result = XwmsApiHelper::authenticateAndSyncUser([
     *     'login' => false,
     *     'remember' => false,
     * ]);
     * // your session logic here
     *
     * 4) Sync logged-in user later
     * --------------------------------------------------
     * $result = XwmsApiHelper::syncAuthenticatedUserFromXwms([
     *     'image_sync' => [
     *         'enabled' => true,
     *         'disk' => 'public',
     *         'directory' => 'users/xwms',
     *         'delete_old' => true,
     *     ],
     * ]);
     *
     * 5) Use custom auth payload for token verify
     * --------------------------------------------------
     * XwmsApiHelper::authenticateAndSyncUser([
     *     'auth_payload' => [
     *         'extra' => 'value',
     *     ],
     * ]);
     */
    private static string|null $clientId = null;
    private static string|null $clientSecret = null;
    private static string|null $clientDomain = null;
    private static Client $httpClient;
    private static string|null $baseUri = null;
    private static string|null $redirectUri = null;

    public static function setup(): void
    {
        self::$baseUri = config("xwms.xwms_api_url", env("XWMS_API_URI", "https://xwms.nl/api/"));
        self::$clientId = config("xwms.client_id", env("XWMS_CLIENT_ID"));
        self::$clientSecret = config("xwms.client_secret", env("XWMS_CLIENT_SECRET"));
        self::$clientDomain = request()?->getHost() ?? config("xwms.client_domain", env("XWMS_DOMAIN"));

        self::$httpClient = new Client([
            'base_uri' => self::$baseUri,
            'timeout'  => config("xwms.xwms_api_timeout", 10),
        ]);
    }

    protected static function postToEndpoint(string $endpoint, array $payload, array $options = []): array
    {
        $payload['redirect_url'] = $payload['redirect_url'] ??= config("xwms.client_redirect", env("XWMS_REDIRECT_URI"));
        if (!self::$httpClient || !self::$clientId || !self::$clientSecret || !$payload['redirect_url']) {
            throw new Exception('XwmsApiHelper not initialized. Make sure ENV XWMS_CLIENT_ID and XWMS_CLIENT_SECRET and XWMS_REDIRECT_URI are set.');
        }

        try {
            $headers = [];
            if (empty($options['no_headers'])) {
                $headers = $options['headers'] ?? [
                    'X-Client-Id'     => self::$clientId,
                    'X-Client-Secret' => self::$clientSecret,
                    'X-Client-Domain' => self::$clientDomain,
                    'Accept'          => 'application/json',
                ];
            }

            $response = self::$httpClient->post($endpoint, [
                'headers' => $headers,
                'json' => $payload,
            ]);

            $json = json_decode((string) $response->getBody(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON response");
            }

            return $json;
        } catch (RequestException|Throwable $e) {
            $msg = $e->getMessage();
            if ($e instanceof RequestException) {
                $msg = $e->hasResponse() ? (string) $e->getResponse()->getBody() : $e->getMessage();
            }
            throw new Exception("API request to {$endpoint} failed: " . $msg);
        }
    }

    protected static function getFromEndpoint(string $endpoint, array $query = [], array $options = []): array
    {
        if (!self::$httpClient || !self::$clientId || !self::$clientSecret) {
            throw new Exception('XwmsApiHelper not initialized. Make sure ENV XWMS_CLIENT_ID and XWMS_CLIENT_SECRET are set.');
        }

        try {
            $headers = [];
            if (empty($options['no_headers'])) {
                $headers = $options['headers'] ?? [
                    'X-Client-Id'     => self::$clientId,
                    'X-Client-Secret' => self::$clientSecret,
                    'X-Client-Domain' => self::$clientDomain,
                    'Accept'          => 'application/json',
                ];
            }

            $response = self::$httpClient->get($endpoint, [
                'headers' => $headers,
                'query'   => $query,
            ]);

            $json = json_decode((string) $response->getBody(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON response");
            }

            return $json;
        } catch (RequestException|Throwable $e) {
            $msg = $e instanceof RequestException && $e->hasResponse()
                ? (string) $e->getResponse()->getBody()
                : $e->getMessage();

            throw new Exception("API GET request to {$endpoint} failed: " . $msg);
        }
    }

    public static function authenticateUser(array $data = []): self
    {
        self::setup();
        $response = (array) self::postToEndpoint("sign-token", $data);

        $instance = new self();
        if (isset($response['data']['url'])) {
            self::$redirectUri = $response['data']['url'];
        } elseif (isset($response['redirect_url'])) {
            self::$redirectUri = $response['redirect_url'];
        } else {
            throw new Exception("Could not get Api Ridirect: ".json_encode($response)."");
        }

        return $instance;
    }

    public static function getAuthenticateUser(array $data = []): array
    {
        self::setup();
        $token = request('token');
        $response = (array) self::postToEndpoint("sign-token-verify", array_merge(['token' => $token], $data));
        return $response;
    }

    public function redirect(): string|null
    {
        return self::$redirectUri;
    }

    public function auth()
    {
        self::authenticateUser();
        $uri = self::redirect();
        return redirect()->to($uri);
    }

    public function authValidate()
    {
        return self::getAuthenticateUser();
    }

    public function info(): array
    {
        self::setup();
        return (array) self::getFromEndpoint("info");
    }

    public static function getUserAddress(string|int $sub, array $data = []): array
    {
        self::setup();
        $response = (array) self::postToEndpoint("get/user/address", array_merge(['sub' => (int) $sub], $data));
        return $response;
    }

    public static function getUserInfo(string|int $sub, array $data = []): array
    {
        self::setup();
        $response = (array) self::postToEndpoint("get/user/info", array_merge(['sub' => (int) $sub], $data));
        return $response;
    }

    public static function getCountries(array $data = []): array
    {
        self::setup();
        return (array) self::postToEndpoint("global/countries", $data);
    }

    public static function getProjects(array $data = []): array
    {
        self::setup();
        return (array) self::getFromEndpoint("global/projects", $data);
    }

    public static function userAddressCrud(string|int $sub, string $action, array $data = []): array
    {
        self::setup();
        $payload = array_merge([
            'sub' => (int) $sub,
            'action' => $action,
        ], $data);

        return (array) self::postToEndpoint("user/address", $payload);
    }

    protected static function ensureXwmsConnectionTable(): void
    {
        $connectionClass = self::resolveModelClass('XwmsConnection', '\\XWMS\\Package\\Models\\XwmsConnection');
        $tableName = (new $connectionClass())->getTable();

        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('sub')->unique();
            $table->timestamps();
        });
    }

    protected static function resolveModelClass(string $configKey, string $fallback): string
    {
        $class = config("xwms.models.{$configKey}", $fallback);
        if (!is_string($class) || !class_exists($class)) {
            if (is_string($fallback) && class_exists($fallback)) {
                return $fallback;
            }
            throw new Exception("Model class for {$configKey} is not configured or does not exist.");
        }

        return $class;
    }

    protected static function extractUserData(array $response): array
    {
        $data = $response['data'] ?? [];
        if (isset($data['user']) && is_array($data['user'])) {
            return $data['user'];
        }

        return is_array($data) ? $data : [];
    }

    protected static function defaultFieldMap(): array
    {
        return config('xwms.user_field_map', [
            'name' => 'name',
            'email' => 'email',
            'img' => 'picture',
        ]);
    }

    protected static function mapUserAttributes(array $userData, array $options = []): array
    {
        $map = $options['field_map'] ?? self::defaultFieldMap();
        $transforms = $options['field_transforms'] ?? config('xwms.user_field_transforms', []);
        $skipNulls = $options['skip_nulls'] ?? true;

        $attributes = [];
        foreach ($map as $local => $source) {
            $value = null;

            if (is_callable($source)) {
                $value = $source($userData, $options);
            } elseif (is_string($source) && $source !== '') {
                $value = data_get($userData, $source);
            } else {
                $value = $userData[$local] ?? null;
            }

            if (isset($transforms[$local]) && is_callable($transforms[$local])) {
                $value = $transforms[$local]($value, $userData, $options);
            }

            if ($local === 'img') {
                $value = self::syncUserImage($value, $options['user'] ?? null, $options);
                if ($skipNulls && $value === null) {
                    continue;
                }
            } else {
                if ($skipNulls && $value === null) {
                    continue;
                }
            }

            $attributes[$local] = $value;
        }

        if (!array_key_exists('name', $attributes)) {
            $fallbackName = $userData['name'] ?? trim(($userData['given_name'] ?? '') . ' ' . ($userData['family_name'] ?? ''));
            if ($fallbackName !== '') {
                $attributes['name'] = $fallbackName;
            }
        }

        return $attributes;
    }

    protected static function resolveImageSyncOptions(array $options = []): array
    {
        $defaults = [
            'enabled' => false,
            'disk' => 'public',
            'directory' => 'users/xwms',
            'delete_old' => true,
            'timeout' => 10,
            'max_kb' => null,
        ];

        $config = config('xwms.user_image_sync', []);
        $overrides = $options['image_sync'] ?? [];

        return array_merge($defaults, $config, $overrides);
    }

    protected static function syncUserImage(mixed $value, ?object $user, array $options = []): mixed
    {
        $opts = self::resolveImageSyncOptions($options);
        if (!($opts['enabled'] ?? false)) {
            return $value;
        }

        if (!is_string($value) || $value === '') {
            if ($user && ($opts['delete_old'] ?? true)) {
                self::deleteStoredImage($user->img ?? null, $opts);
            }
            return null;
        }

        if (!self::isRemoteUrl($value)) {
            return $value;
        }

        $storedPath = self::downloadRemoteImage($value, $opts);
        if ($storedPath === null) {
            return $user?->img ?? null;
        }

        if ($user && ($opts['delete_old'] ?? true)) {
            self::deleteStoredImage($user->img ?? null, $opts, $storedPath);
        }

        return $storedPath;
    }

    protected static function isRemoteUrl(string $value): bool
    {
        return Str::startsWith($value, ['http://', 'https://', '//']);
    }

    protected static function downloadRemoteImage(string $url, array $options = []): ?string
    {
        $normalizedUrl = Str::startsWith($url, '//') ? "https:{$url}" : $url;

        try {
            $client = new Client([
                'timeout' => $options['timeout'] ?? 10,
                'http_errors' => false,
            ]);

            $response = $client->get($normalizedUrl);
            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                return null;
            }

            $body = (string) $response->getBody();
            $maxKb = $options['max_kb'] ?? null;
            if (is_int($maxKb) && $maxKb > 0 && strlen($body) > ($maxKb * 1024)) {
                return null;
            }

            $contentType = $response->getHeaderLine('Content-Type');
            $extension = self::extensionFromContentType($contentType);

            $disk = $options['disk'] ?? 'public';
            $directory = trim((string) ($options['directory'] ?? ''), '/');
            $filename = 'xwms_' . Str::uuid() . '.' . $extension;
            $path = $directory !== '' ? "{$directory}/{$filename}" : $filename;

            Storage::disk($disk)->put($path, $body);

            return $path;
        } catch (Throwable) {
            return null;
        }
    }

    protected static function extensionFromContentType(string $contentType): string
    {
        $contentType = strtolower(trim(explode(';', $contentType)[0] ?? ''));
        return match ($contentType) {
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'image/jpeg', 'image/jpg' => 'jpg',
            default => 'jpg',
        };
    }

    protected static function deleteStoredImage(?string $path, array $options = [], ?string $keepPath = null): void
    {
        if (!$path || self::isRemoteUrl($path)) {
            return;
        }

        if ($keepPath !== null && $path === $keepPath) {
            return;
        }

        $disk = $options['disk'] ?? 'public';
        if (Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }

    protected static function applyUserAttributes(object $user, array $attributes): array
    {
        $dirty = [];
        foreach ($attributes as $key => $value) {
            if (!property_exists($user, $key) && !method_exists($user, '__get')) {
                continue;
            }

            if ($user->{$key} !== $value) {
                $user->{$key} = $value;
                $dirty[$key] = $value;
            }
        }

        if (!empty($dirty)) {
            $user->save();
        }

        return [
            'changed' => !empty($dirty),
            'dirty' => $dirty,
        ];
    }

    protected static function buildNewUserAttributes(array $userData, array $options = []): array
    {
        $attributes = self::mapUserAttributes($userData, $options);

        if (($options['set_random_password'] ?? true) === true) {
            $length = (int) ($options['password_length'] ?? 40);
            $attributes['password'] = bcrypt(Str::random($length));
        }

        return $attributes;
    }

    protected static function resolveConnectionBySub(string $connectionClass, string $sub): object|null
    {
        if (method_exists($connectionClass, 'findBySub')) {
            return $connectionClass::findBySub($sub);
        }

        return $connectionClass::query()->where('sub', $sub)->first();
    }

    protected static function connectUserToSub(string $connectionClass, object $user, string $sub): object
    {
        if (method_exists($connectionClass, 'connectUser')) {
            return $connectionClass::connectUser($user, $sub);
        }

        return $connectionClass::query()->firstOrCreate([
            'sub' => $sub,
        ], [
            'user_id' => $user->id,
        ]);
    }

    protected static function getSubForUser(string $connectionClass, object $user): string|null
    {
        if (method_exists($connectionClass, 'getSubForAuthenticatedUser')) {
            return $connectionClass::getSubForAuthenticatedUser();
        }

        return $connectionClass::query()->where('user_id', $user->id)->value('sub');
    }

    public static function authenticateAndSyncUser(array $options = []): array
    {
        try {
            self::ensureXwmsConnectionTable();
            $response = self::getAuthenticateUser($options['auth_payload'] ?? []);

            if (($response['status'] ?? null) !== 'success') {
                return [
                    'status' => 'error',
                    'message' => 'Authentication failed.',
                    'response' => $response,
                ];
            }

            $userData = self::extractUserData($response);
            $sub = $userData['sub'] ?? null;
            if (!$sub) {
                return [
                    'status' => 'error',
                    'message' => 'Missing sub in XWMS response.',
                    'response' => $response,
                ];
            }

            $userClass = self::resolveModelClass('User', '\\App\\Models\\User');
            $connectionClass = self::resolveModelClass('XwmsConnection', '\\XWMS\\Package\\Models\\XwmsConnection');

            $connection = self::resolveConnectionBySub($connectionClass, $sub);
            $user = $connection?->user ?? null;
            $action = null;

            $linkByEmail = $options['link_by_email'] ?? true;
            $createUser = $options['create_user'] ?? true;
            $updateExisting = $options['update_existing'] ?? true;

            if ($user) {
                $action = 'existing_connection';
                if ($updateExisting) {
                    $optionsWithUser = array_merge($options, ['user' => $user]);
                    $attributes = self::mapUserAttributes($userData, $optionsWithUser);
                    self::applyUserAttributes($user, $attributes);
                    $action = 'updated_existing_user';
                }
            } else {
                $email = $userData['email'] ?? null;
                if ($linkByEmail && $email) {
                    $user = $userClass::query()->where('email', $email)->first();
                    if ($user) {
                        $action = 'linked_by_email';
                        $optionsWithUser = array_merge($options, ['user' => $user]);
                        $attributes = self::mapUserAttributes($userData, $optionsWithUser);
                        self::applyUserAttributes($user, $attributes);
                    }
                }

                if (!$user && $createUser) {
                    $attributes = self::buildNewUserAttributes($userData, $options);
                    $user = $userClass::query()->create($attributes);
                    $action = 'created_user';
                }
            }

            if (!$user) {
                return [
                    'status' => 'error',
                    'message' => 'No local user could be resolved or created.',
                    'response' => $response,
                ];
            }

            $connection = self::connectUserToSub($connectionClass, $user, (string) $sub);

            if (($options['login'] ?? true) === true) {
                Auth::login($user, (bool) ($options['remember'] ?? true));
            }

            return [
                'status' => 'success',
                'message' => 'User authenticated and synchronized.',
                'action' => $action,
                'user' => $user,
                'connection' => $connection,
                'response' => $response,
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'message' => 'Authentication failed due to a server error.',
                'error' => $e->getMessage(),
            ];
        }
    }

    public static function syncAuthenticatedUserFromXwms(array $options = []): array
    {
        try {
            self::ensureXwmsConnectionTable();

            $user = $options['user'] ?? Auth::user();
            if (!$user) {
                return [
                    'status' => 'error',
                    'message' => 'No authenticated user found.',
                ];
            }

            $connectionClass = self::resolveModelClass('XwmsConnection', '\\XWMS\\Package\\Models\\XwmsConnection');
            $sub = self::getSubForUser($connectionClass, $user);

            if (!$sub) {
                return [
                    'status' => 'error',
                    'message' => 'No XWMS connection found for this user.',
                ];
            }

            $response = self::getUserInfo($sub, $options['user_info_payload'] ?? []);
            if (($response['status'] ?? null) !== 'success') {
                return [
                    'status' => 'error',
                    'message' => 'Failed to fetch user data from XWMS.',
                    'response' => $response,
                ];
            }

            $userData = self::extractUserData($response);
            $optionsWithUser = array_merge($options, ['user' => $user]);
            $attributes = self::mapUserAttributes($userData, $optionsWithUser);
            $result = self::applyUserAttributes($user, $attributes);

            return [
                'status' => 'success',
                'message' => $result['changed'] ? 'User updated from XWMS.' : 'No changes detected.',
                'user' => $user,
                'response' => $response,
                'changes' => $result['dirty'],
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'message' => 'User sync failed due to a server error.',
                'error' => $e->getMessage(),
            ];
        }
    }
}
