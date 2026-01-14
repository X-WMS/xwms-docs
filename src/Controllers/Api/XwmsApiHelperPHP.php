<?php

namespace XWMS\Package\Controllers\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Exception;

class XwmsApiHelperPHP
{
    private string|null $clientId = null;
    private string|null $clientSecret = null;
    private string|null $clientDomain = null;
    private Client $httpClient;
    private string|null $baseUri = null;
    private string|null $redirectUri = null;
    private array $config = [];

    public function __construct(
        ?string $clientId = null,
        ?string $clientSecret = null,
        ?Client $httpClient = null,
        ?string $clientDomain = null,
        ?string $baseUri = null,
        ?array $config = null
    ) {
        $this->config = $config ?? $this->loadConfig();

        $this->clientId = $clientId ?? ($this->config["client_id"] ?? getenv("XWMS_CLIENT_ID"));
        $this->clientSecret = $clientSecret ?? ($this->config["client_secret"] ?? getenv("XWMS_CLIENT_SECRET"));
        $this->clientDomain = $clientDomain ?? ($this->config["client_domain"] ?? getenv("XWMS_DOMAIN"));
        $this->baseUri = rtrim($baseUri ?? ($this->config["xwms_api_url"] ?? getenv("XWMS_API_URI")), '/') . '/';

        $this->httpClient = $httpClient ?: new Client([
            'base_uri' => $this->baseUri,
            'timeout' => 10,
        ]);
    }

    protected function loadConfig(): array
    {
        $candidates = [
            getcwd() . '/config/xwms.php',
            dirname(getcwd()) . '/config/xwms.php',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                $config = require $path;
                return is_array($config) ? $config : [];
            }
        }

        return [];
    }

    protected function postToEndpoint(string $endpoint, array $payload): array
    {
        if (!$this->clientId || !$this->clientSecret || !$this->clientDomain) {
            throw new Exception("Missing required client configuration.");
        }

        $redirect = $this->config["client_redirect"] ?? getenv("XWMS_REDIRECT_URI");
        $payload['redirect_url'] ??= $redirect ?: 'http://localhost/xwms/validateToken';

        try {
            $response = $this->httpClient->post($endpoint, [
                'headers' => $this->getDefaultHeaders(),
                'json' => $payload,
            ]);

            return $this->parseJsonResponse($response->getBody());
        } catch (RequestException $e) {
            $msg = $e->hasResponse()
                ? (string) $e->getResponse()->getBody()
                : $e->getMessage();
            throw new Exception("POST {$endpoint} failed: {$msg}");
        }
    }

    protected function getFromEndpoint(string $endpoint, array $query = []): array
    {
        if (!$this->clientId || !$this->clientSecret || !$this->clientDomain) {
            throw new Exception("Missing required client configuration.");
        }

        try {
            $response = $this->httpClient->get($endpoint, [
                'headers' => $this->getDefaultHeaders(),
                'query' => $query,
            ]);

            return $this->parseJsonResponse($response->getBody());
        } catch (RequestException $e) {
            $msg = $e->hasResponse()
                ? (string) $e->getResponse()->getBody()
                : $e->getMessage();
            throw new Exception("GET {$endpoint} failed: {$msg}");
        }
    }

    protected function getDefaultHeaders(): array
    {
        return [
            'X-Client-Id'     => $this->clientId,
            'X-Client-Secret' => $this->clientSecret,
            'X-Client-Domain' => $this->clientDomain,
            'Accept'          => 'application/json',
        ];
    }

    protected function parseJsonResponse($body): array
    {
        $json = json_decode((string) $body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response");
        }
        return $json;
    }

    protected function extractUserData(array $response): array
    {
        $data = $response['data'] ?? [];
        if (isset($data['user']) && is_array($data['user'])) {
            return $data['user'];
        }
        return is_array($data) ? $data : [];
    }

    protected function mapUserAttributes(array $userData, array $options = []): array
    {
        $map = $options['field_map'] ?? [
            'name' => 'name',
            'email' => 'email',
            'picture' => 'picture',
        ];
        $transforms = $options['field_transforms'] ?? [];
        $skipNulls = $options['skip_nulls'] ?? true;

        $attributes = [];
        foreach ($map as $local => $source) {
            $value = null;

            if (is_callable($source)) {
                $value = $source($userData, $options);
            } elseif (is_string($source) && $source !== '') {
                $value = $userData[$source] ?? null;
            } else {
                $value = $userData[$local] ?? null;
            }

            if (isset($transforms[$local]) && is_callable($transforms[$local])) {
                $value = $transforms[$local]($value, $userData, $options);
            }

            if ($local === 'picture') {
                $value = $this->syncUserImage($value, $options['current_picture'] ?? null, $options['image_sync'] ?? []);
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

    protected function syncUserImage(?string $url, ?string $current, array $options = []): ?string
    {
        $enabled = $options['enabled'] ?? false;
        if (!$enabled) return $url ?: $current;

        if (!$url || !preg_match('/^https?:\\/\\//i', $url)) {
            if (!empty($options['delete_old']) && $current && is_file($current)) {
                @unlink($current);
            }
            return $url ?: $current;
        }

        $dir = $options['directory'] ?? (getcwd() . '/public/xwms');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $ext = $this->extensionFromContentType($this->getRemoteContentType($url));
        $filename = 'xwms_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $path = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $filename;

        $data = @file_get_contents($url);
        if ($data === false) {
            return $current;
        }

        file_put_contents($path, $data);
        if (!empty($options['delete_old']) && $current && is_file($current) && $current !== $path) {
            @unlink($current);
        }

        return $path;
    }

    protected function getRemoteContentType(string $url): string
    {
        $headers = @get_headers($url, 1);
        if (!$headers) return '';
        $contentType = $headers['Content-Type'] ?? '';
        if (is_array($contentType)) {
            return (string) $contentType[0];
        }
        return (string) $contentType;
    }

    protected function extensionFromContentType(string $contentType): string
    {
        $normalized = strtolower(trim(explode(';', $contentType)[0] ?? ''));
        if ($normalized === 'image/png') return 'png';
        if ($normalized === 'image/webp') return 'webp';
        if ($normalized === 'image/gif') return 'gif';
        if ($normalized === 'image/svg+xml') return 'svg';
        if ($normalized === 'image/jpeg' || $normalized === 'image/jpg') return 'jpg';
        return 'jpg';
    }

    /**
     * Initiate authentication and store redirect URL
     */
    public function authenticateUser(array $data = []): self
    {
        $response = $this->postToEndpoint("sign-token", $data);

        if (!empty($response['data']['url'])) {
            $this->redirectUri = $response['data']['url'];
        } elseif (!empty($response['redirect_url'])) {
            $this->redirectUri = $response['redirect_url'];
        } else {
            throw new Exception("Could not get redirect URL from response.");
        }

        return $this;
    }

    /**
     * Verify a token returned from redirect
     */
    public function getAuthenticateUser(string $token, array $data = []): array
    {
        return $this->postToEndpoint("sign-token-verify", array_merge(['token' => $token], $data));
    }

    /**
     * Get the redirect URL after authentication
     */
    public function redirect(): ?string
    {
        return $this->redirectUri;
    }

    /**
     * Get general info from the API
     */
    public function info(): array
    {
        return $this->getFromEndpoint("info");
    }

    /**
     * Get address info for a specific user by email
     */
    public function getUserAddress(string|int $sub, array $data = []): array
    {
        return $this->postToEndpoint("get/user/address", array_merge(['sub' => (int) $sub], $data));
    }

    public function getUserInfo(string|int $sub, array $data = []): array
    {
        return $this->postToEndpoint("get/user/info", array_merge(['sub' => (int) $sub], $data));
    }

    public function auth(): void
    {
        $this->authenticateUser([
            'redirect_url' => $this->config["client_redirect"] ?? getenv("XWMS_REDIRECT_URI")
        ]);

        $uri = $this->redirect();
        if (!$uri) {
            throw new Exception("Redirect URL not set after authentication.");
        }

        header("Location: {$uri}");
        exit;
    }

    /**
     * Valideer de gebruiker na redirect via token (bijv. $_GET['token'])
     */
    public function authValidate(): array
    {
        $token = $_GET['token'] ?? null;

        if (!$token) {
            throw new Exception("Missing token in request.");
        }

        return $this->getAuthenticateUser($token);
    }

    public function authenticateAndSyncUser(array $options = []): array
    {
        try {
            $token = $options['token'] ?? ($_GET['token'] ?? null);
            if (!$token) {
                return ['status' => 'error', 'message' => 'Missing token'];
            }

            $response = $this->getAuthenticateUser($token, $options['auth_payload'] ?? []);
            if (($response['status'] ?? null) !== 'success') {
                return ['status' => 'error', 'message' => 'Authentication failed', 'response' => $response];
            }

            $userData = $this->extractUserData($response);
            $sub = $userData['sub'] ?? null;
            if (!$sub) {
                return ['status' => 'error', 'message' => 'Missing sub in response'];
            }

            $findConnection = $options['find_connection_by_sub'] ?? null;
            $findUserByEmail = $options['find_user_by_email'] ?? null;
            $createUser = $options['create_user'] ?? null;
            $updateUser = $options['update_user'] ?? null;
            $connectUser = $options['connect_user'] ?? null;

            if (!is_callable($findConnection) || !is_callable($createUser) || !is_callable($connectUser)) {
                return ['status' => 'error', 'message' => 'Missing required callbacks'];
            }

            $connection = $findConnection($sub);
            $user = $connection['user'] ?? ($connection->user ?? null);
            $action = null;

            if ($user && is_callable($updateUser)) {
                $attributes = $this->mapUserAttributes($userData, [
                    'image_sync' => $options['image_sync'] ?? [],
                    'current_picture' => $user['picture'] ?? ($user->picture ?? null),
                ]);
                $user = $updateUser($user, $attributes);
                $action = 'updated_existing_user';
            }

            if (!$user && is_callable($findUserByEmail) && !empty($userData['email'])) {
                $user = $findUserByEmail($userData['email']);
                if ($user && is_callable($updateUser)) {
                    $attributes = $this->mapUserAttributes($userData, [
                        'image_sync' => $options['image_sync'] ?? [],
                        'current_picture' => $user['picture'] ?? ($user->picture ?? null),
                    ]);
                    $user = $updateUser($user, $attributes);
                    $action = 'linked_by_email';
                }
            }

            if (!$user) {
                $attributes = $this->mapUserAttributes($userData, [
                    'image_sync' => $options['image_sync'] ?? [],
                ]);
                $user = $createUser($attributes);
                $action = 'created_user';
            }

            $connection = $connectUser($user, (string) $sub);
            if (is_callable($options['on_login'] ?? null)) {
                ($options['on_login'])($user, $connection, $response);
            }

            return [
                'status' => 'success',
                'message' => 'User authenticated and synchronized.',
                'action' => $action,
                'user' => $user,
                'connection' => $connection,
                'response' => $response,
            ];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'Authentication failed', 'error' => $e->getMessage()];
        }
    }

    public function syncAuthenticatedUserFromXwms(array $options = []): array
    {
        try {
            $user = $options['user'] ?? null;
            $getSub = $options['get_sub_for_user'] ?? null;
            $updateUser = $options['update_user'] ?? null;

            if (!$user || !is_callable($getSub) || !is_callable($updateUser)) {
                return ['status' => 'error', 'message' => 'Missing user or callbacks'];
            }

            $sub = $getSub($user);
            if (!$sub) {
                return ['status' => 'error', 'message' => 'No XWMS connection for user'];
            }

            $response = $this->getUserInfo($sub, $options['user_info_payload'] ?? []);
            if (($response['status'] ?? null) !== 'success') {
                return ['status' => 'error', 'message' => 'Failed to fetch user', 'response' => $response];
            }

            $userData = $this->extractUserData($response);
            $attributes = $this->mapUserAttributes($userData, [
                'image_sync' => $options['image_sync'] ?? [],
                'current_picture' => $user['picture'] ?? ($user->picture ?? null),
            ]);
            $user = $updateUser($user, $attributes);

            return [
                'status' => 'success',
                'message' => 'User updated from XWMS.',
                'user' => $user,
                'response' => $response,
                'changes' => $attributes,
            ];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'User sync failed', 'error' => $e->getMessage()];
        }
    }
}
