<?php

namespace ec5\Traits\Auth;

use ec5\Http\Validation\Auth\RuleTurnstile;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

trait TurnstileValidation
{
    /**
     * Verify a Turnstile response token by sending it to Cloudflare's verification endpoint.
     *
     * @param string $turnstileResponse The Turnstile response token from the client
     * @return array An array of errors (if any), or an empty array if verification succeeds
     * @throws Throwable
     */
    public function getAnyTurnstileErrors(string $turnstileResponse): array
    {
        $ruleTurnstile = new RuleTurnstile();
        $verifyEndpoint = config('epicollect.setup.cloudflare_turnstile.verify_endpoint');

        try {
            $response = Http::timeout(5)
                ->connectTimeout(2)
                ->asForm()
                ->post($verifyEndpoint, [
                    'secret' => config('epicollect.setup.cloudflare_turnstile.secret_key'),
                    'response' => $turnstileResponse,
                    'remoteip' => request()->ip()
                ]);

            $arrayResponse = $response->json();
            if (!is_array($arrayResponse)) {
                return ['captcha' => ['ec5_380']];
            }

            $ruleTurnstile->validate($arrayResponse);
            if ($ruleTurnstile->hasErrors()) {
                return $ruleTurnstile->errors();
            }

            $ruleTurnstile->additionalChecks($arrayResponse);
            if ($ruleTurnstile->hasErrors()) {
                return $ruleTurnstile->errors();
            }

            return [];
        } catch (ConnectionException $e) {
            \Log::error('Turnstile verification connection error', ['exception' => $e->getMessage()]);
            return ['captcha' => ['ec5_103']];
        } catch (Throwable $e) {
            \Log::error('Turnstile verification error', ['exception' => $e->getMessage()]);
            throw $e;
        }
    }
}
