<?php

namespace App\Services;

use Illuminate\Http\Client\Response;

class GoogleCalendarFailure extends \RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly array $diagnostics = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($reason, 0, $previous);
    }

    public static function fromException(string $operation, \Throwable $exception): self
    {
        $diagnostics = ['operation' => $operation];
        for ($cause = $exception; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof \GuzzleHttp\Exception\ConnectException) {
                $errno = $cause->getHandlerContext()['errno'] ?? null;
                if (is_int($errno)) {
                    $diagnostics['curl_errno'] = $errno;
                }
                break;
            }
        }

        return new self('provider_unavailable', $diagnostics, $exception);
    }

    public static function fromResponse(string $reason, string $operation, Response $response): self
    {
        $body = $response->json();
        $error = is_array($body) ? ($body['error'] ?? null) : null;
        $code = is_array($error) ? data_get($error, 'errors.0.reason') : $error;
        // Never log provider messages or arbitrary codes: they can contain customer data.
        $allowed = ['invalid_grant', 'invalid_client', 'unauthorized_client', 'invalid_request',
            'invalid_scope', 'access_denied', 'temporarily_unavailable', 'server_error',
            'rateLimitExceeded', 'userRateLimitExceeded', 'quotaExceeded', 'dailyLimitExceeded',
            'backendError', 'internalError', 'authError', 'forbidden', 'insufficientPermissions',
            'notFound', 'gone', 'duplicate', 'invalid', 'badRequest', 'conditionNotMet'];
        $retry = $response->header('Retry-After');

        return new self($reason, [
            'operation' => $operation,
            'http_status' => $response->status(),
            'provider_code' => in_array($code, $allowed, true) ? $code : ($code === null ? null : 'unrecognized'),
            'retry_after_seconds' => is_string($retry) && preg_match('/^\d{1,6}$/D', $retry) ? (int) $retry : null,
        ]);
    }
}
