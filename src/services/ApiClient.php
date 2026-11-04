<?php

declare(strict_types=1);

namespace coworkingview\coworkingview\services;

use Craft;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

/**
 * Read-only client for `https://api.coworkingview.com`. No API key.
 */
class ApiClient
{
    private const USER_AGENT = 'coworkingview-craft/1.0.0 (+https://github.com/coworkingview/craft-coworkingview)';

    /**
     * @return array<string, mixed>
     */
    public function marketRates(?string $city, ?string $country, ?string $currency, string $locale): array
    {
        return $this->get('/v1/market/rates', [
            'locale' => $locale,
            'city' => $city,
            'country' => $country,
            'currency' => $currency,
        ]);
    }

    /**
     * @param array<string, string|null> $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query): array
    {
        $attempt = 0;

        while (true) {
            try {
                $response = Craft::createGuzzleClient([
                    'headers' => [
                        'Accept' => 'application/json',
                        'User-Agent' => self::USER_AGENT,
                    ],
                    'timeout' => 20,
                    'connect_timeout' => 10,
                    'http_errors' => false,
                ])->request('GET', $this->endpoint() . $path, [
                    'query' => $this->query($query),
                ]);
            } catch (GuzzleException $e) {
                throw new ApiException(
                    'CoworkingView could not be reached.',
                    0,
                    'CONNECTION',
                    'Connection failed',
                    $e->getMessage(),
                    $e,
                );
            }

            $status = $response->getStatusCode();
            $body = (string)$response->getBody();

            if ($status >= 200 && $status < 300) {
                $decoded = json_decode($body, true);
                if (!is_array($decoded)) {
                    throw new ApiException(
                        'CoworkingView returned a response that was not JSON.',
                        $status,
                        'BAD_RESPONSE',
                        'Bad response',
                        'Response was not JSON.',
                    );
                }

                /** @var array<string, mixed> $decoded */
                return $decoded;
            }

            if ($attempt === 0 && ($status === 429 || $status >= 500)) {
                $attempt++;
                $this->pause($response);
                continue;
            }

            throw $this->problem($status, $body);
        }
    }

    /**
     * @param array<string, string|null> $query
     * @return array<string, string>
     */
    private function query(array $query): array
    {
        $out = [];
        foreach ($query as $key => $value) {
            if ($value !== null && $value !== '') {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    private function endpoint(): string
    {
        $base = getenv('COWORKINGVIEW_API_URL');
        if (!is_string($base) || trim($base) === '') {
            return 'https://api.coworkingview.com';
        }

        return rtrim(trim($base), '/');
    }

    private function pause(ResponseInterface $response): void
    {
        $seconds = 2;
        $retryAfter = $response->getHeaderLine('Retry-After');
        if (is_numeric($retryAfter)) {
            $seconds = (int)$retryAfter;
        } else {
            $reset = $response->getHeaderLine('RateLimit-Reset');
            if (is_numeric($reset)) {
                $value = (int)$reset;
                $seconds = $value > 3600 ? max(0, $value - time()) : $value;
            }
        }

        $seconds = max(0, min(10, $seconds));
        if ($seconds > 0) {
            sleep($seconds);
        }
    }

    private function problem(int $status, string $body): ApiException
    {
        $decoded = json_decode($body, true);
        $code = 'HTTP_' . $status;
        $title = 'HTTP ' . $status;
        $detail = $title;

        if (is_array($decoded)) {
            if (isset($decoded['code']) && is_string($decoded['code']) && $decoded['code'] !== '') {
                $code = $decoded['code'];
            }
            if (isset($decoded['title']) && is_string($decoded['title']) && $decoded['title'] !== '') {
                $title = $decoded['title'];
            }
            if (isset($decoded['detail']) && is_string($decoded['detail']) && $decoded['detail'] !== '') {
                $detail = $decoded['detail'];
            }
        }

        return new ApiException($detail, $status, $code, $title, $detail);
    }
}
