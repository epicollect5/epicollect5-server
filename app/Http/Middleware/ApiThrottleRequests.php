<?php

namespace ec5\Http\Middleware;

use Closure;
use Carbon\Carbon;
use ec5\Mail\ExceptionNotificationMail;
use Exception;
use Illuminate\Cache\RateLimiter;
use Log;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Mail;
use ec5\Traits\Middleware\MiddlewareTools;

class ApiThrottleRequests
{
    use MiddlewareTools;

    /**
     * The rate limiter instance.
     */
    protected $limiter;

    /**
     * ApiThrottleRequests constructor.
     */
    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Handle an incoming request.
     */
    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1): Response
    {
        $key = $this->resolveRequestSignature($request);

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            return $this->buildResponse($request, $key, $maxAttempts);
        }

        try {
            $this->limiter->hit($key, $decayMinutes);
        } catch (\Throwable $e) {
            Log::error('Rate limiter hit() exception', ['message' => $e->getMessage()]);
            Mail::to(config('epicollect.setup.system.email'))->send(new ExceptionNotificationMail($e->getMessage()));
        }

        $response = $next($request);

        return $this->addHeaders(
            $response,
            $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts)
        );
    }

    /**
     * Resolve request signature.
     */
    protected function resolveRequestSignature($request): string
    {
        return $request->fingerprint();
    }

    /**
     * Create a 'too many attempts' response.
     *
     * @param $request
     * @param $key
     * @param $maxAttempts
     * @return Response
     */
    protected function buildResponse($request, $key, $maxAttempts): Response
    {
        $response = $this->middlewareErrorResponse($request, 'ec5_255', 429);
        $retryAfter = $this->limiter->availableIn($key);

        return $this->addHeaders(
            $response,
            $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts, $retryAfter),
            $retryAfter
        );
    }

    /**
     * Add the limit header information to the given response.
     */
    protected function addHeaders(Response $response, $maxAttempts, $remainingAttempts, $retryAfter = null): Response
    {
        $headers = [
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remainingAttempts,
        ];

        if (!is_null($retryAfter)) {
            $headers['Retry-After'] = $retryAfter;
            $headers['X-RateLimit-Reset'] = Carbon::now()->getTimestamp() + $retryAfter;
        }

        $response->headers->add($headers);

        return $response;
    }

    /**
     * Calculate the number of remaining attempts.
     */
    protected function calculateRemainingAttempts($key, $maxAttempts, $retryAfter = null): int
    {
        if (!is_null($retryAfter)) {
            return 0;
        }
        return $this->limiter->retriesLeft($key, $maxAttempts);
    }
}
