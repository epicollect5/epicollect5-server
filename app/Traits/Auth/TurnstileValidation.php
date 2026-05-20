<?php

namespace ec5\Traits\Auth;

use ec5\Http\Validation\Auth\RuleTurnstile;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

trait TurnstileValidation
{
    /**
     * @throws GuzzleException
     */
    public function getAnyTurnstileErrors($turnstileResponse): array
    {
        $client = new Client();
        $ruleTurnstile = new RuleTurnstile();
        $verifyEndpoint = config('epicollect.setup.cloudflare_turnstile.verify_endpoint');
        $response = $client->post($verifyEndpoint, [
            'form_params' => [
                'secret' => config('epicollect.setup.cloudflare_turnstile.secret_key'),
                'response' => $turnstileResponse
            ]
        ]);

        $arrayResponse = json_decode($response->getBody()->getContents(), true);

        $ruleTurnstile->validate($arrayResponse);
        if ($ruleTurnstile->hasErrors()) {
            return $ruleTurnstile->errors();
        }

        $ruleTurnstile->additionalChecks($arrayResponse);
        if ($ruleTurnstile->hasErrors()) {
            return $ruleTurnstile->errors();
        }

        return [];
    }
}
