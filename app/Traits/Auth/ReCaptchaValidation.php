<?php

namespace ec5\Traits\Auth;

use ec5\Http\Validation\Auth\RuleRecaptcha;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;
use Log;
use Exception;

trait ReCaptchaValidation
{
    public function getAnyRecaptchaErrors($recaptchaResponse): array
    {
        $client = new Client(); //GuzzleHttp\Client
        $ruleRecaptcha = new RuleRecaptcha();
        $response = $client->post(config('epicollect.setup.google_recaptcha.verify_endpoint'), [
            'form_params' => [
                'secret' => config('epicollect.setup.google_recaptcha.secret_key'),
                'response' => $recaptchaResponse
            ]
        ]);

        /**
         * Validate the captcha response first
         */
        $arrayResponse = json_decode($response->getBody()->getContents(), true);

        $ruleRecaptcha->validate($arrayResponse);
        if ($ruleRecaptcha->hasErrors()) {
            return $ruleRecaptcha->errors();
        }

        $ruleRecaptcha->additionalChecks($arrayResponse);
        if ($ruleRecaptcha->hasErrors()) {
            return $ruleRecaptcha->errors();
        }
        //no errors, empty array
        return [];
    }
}
