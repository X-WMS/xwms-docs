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
        string $clientId,
        string $clientSecret,
        ?Client $httpClient = null,
        ?string $clientDomain = null,
        string $baseUri = "https://xwms.nl/api/"
    ) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->clientDomain = $clientDomain ?? ($_SERVER['HTTP_HOST'] ?? getenv('XWMS_DOMAIN') ?? null);
        $this->baseUri = rtrim($baseUri, '/') . '/';

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

        // Add default redirect_url if not set
        $payload['redirect_url'] ??= getenv('XWMS_REDIRECT_URI') ?? 'http://localhost/xwms/validateToken';

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
    public function getUserAddress(string $email, array $data = []): array
    {
        return $this->postToEndpoint("get/user/address", array_merge(['email' => $email], $data));
    }

    public function auth(): void
    {
        $this->authenticateUser([
            'redirect_url' => getenv('XWMS_REDIRECT_URI')
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
