<?php

namespace XWMS\Package\Controllers\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Exception;
use Throwable;

class XwmsApiHelper
{
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
            if ($e instanceof RequestException){
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

    public static function getUserAddress(string $email, array $data = []): array
    {
        self::setup();
        $response = (array) self::postToEndpoint("get/user/address", array_merge(['email' => $email], $data));
        return $response;
    }
}