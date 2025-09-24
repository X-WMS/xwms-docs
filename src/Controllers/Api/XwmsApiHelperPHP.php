<?php

namespace XWMS\Package\Controllers\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Exception;

class XwmsApiHelperPHP
{
    private string|null  $clientId = null;
    private string|null  $clientSecret = null;
    private string|null $clientDomain = null;
    private Client $httpClient;
    private string|null  $baseUri = null;
    private string|null $redirectUri = null;

    public function __construct(string $clientId, string $clientSecret, ?Client $httpClient = null, ?string $clientDomain = null, string $baseUri = "https://xwms.nl/api/")
    {
        $this->baseUri = $baseUri ?? "https://xwms.nl/api/";
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->clientDomain = $clientDomain ?? ($_SERVER['HTTP_HOST'] ?? getenv('XWMS_DOMAIN') ?? null);

        $this->httpClient = $httpClient ?: new Client([
            'base_uri' => $this->baseUri,
            'timeout'  => 10,
        ]);
    }

    protected function postToEndpoint(string $endpoint, array $payload): array
    {
        if (!$this->httpClient || !$this->clientId || !$this->clientSecret) {
            throw new Exception('XwmsApiHelper not initialized. Make sure ENV XWMS_CLIENT_ID and XWMS_CLIENT_SECRET are set.');
        }

        try {
            $response = $this->httpClient->post($endpoint, [
                'headers' => [
                    'X-Client-Id' => $this->clientId,
                    'X-Client-Secret' => $this->clientSecret,
                    'X-Client-Domain' => $this->clientDomain,
                    'Accept' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $json = json_decode((string) $response->getBody(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON response");
            }

            return $json;
        } catch (RequestException $e) {
            $msg = $e->hasResponse() ? (string) $e->getResponse()->getBody() : $e->getMessage();
            throw new Exception("API request to {$endpoint} failed: " . $msg);
        }
    }

    public function authenticateUser(array $data = []): self
    {
        $response = $this->postToEndpoint("sign-token", $data);

        if (isset($response['data']['url'])) {
            $this->redirectUri = $response['data']['url'];
        } elseif (isset($response['redirect_url'])) {
            $this->redirectUri = $response['redirect_url'];
        } else {
            throw new Exception("Could not get redirect URL from API response: " . json_encode($response));
        }

        return $this;
    }

    public function getAuthenticateUser(string $token, array $data = []): array
    {
        return $this->postToEndpoint("sign-token-verify", array_merge(['token' => $token], $data));
    }

    public function redirect(): ?string
    {
        return $this->redirectUri;
    }
}
