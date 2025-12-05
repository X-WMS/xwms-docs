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

    public function __construct(
        ?string $clientId = null,
        ?string $clientSecret = null,
        ?Client $httpClient = null,
        ?string $clientDomain = null,
        ?string $baseUri = null
    ) {
        $projectRoot = getcwd();
        $configPath = $projectRoot . '../../config/xwms.php';
        $xwmsConfig = require $configPath;

        $this->clientId = $clientId ?? $xwmsConfig["client_id"];
        $this->clientSecret = $clientSecret ?? $xwmsConfig["client_secret"];
        $this->clientDomain = $clientDomain ?? $xwmsConfig["client_domain"];
        $this->baseUri = rtrim($baseUri ?? $xwmsConfig["xwms_api_url"], '/') . '/';

        $this->httpClient = $httpClient ?: new Client([
            'base_uri' => $this->baseUri,
            'timeout' => 10,
        ]);
    }

    protected function postToEndpoint(string $endpoint, array $payload): array
    {
        if (!$this->clientId || !$this->clientSecret || !$this->clientDomain) {
            throw new Exception("Missing required client configuration.");
        }

        $projectRoot = getcwd();
        $configPath = $projectRoot . '../../config/xwms.php';
        $xwmsConfig = require $configPath;

        // Add default redirect_url if not set
        $payload['redirect_url'] ??= $xwmsConfig["client_redirect"] ?? 'http://localhost/xwms/validateToken';

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
        $projectRoot = getcwd();
        $configPath = $projectRoot . '../../config/xwms.php';
        $xwmsConfig = require $configPath;

        $this->authenticateUser([
            'redirect_url' => $xwmsConfig["client_redirect"]
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
}