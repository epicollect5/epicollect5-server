<?php

namespace ec5\Providers\Macros\Response;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;

class ApiErrorCodeMacro extends ServiceProvider
{
    public function boot(): void
    {
        Response::macro('apiErrorCode', function ($httpStatusCode, array $errors, array $extra = []) {
            $parsedErrors = [];
            // loop through $errors and format into an API error array
            foreach ($errors as $key => $error) {
                // temp array to store error, expecting array otherwise skip
                if (is_array($error)) {
                    foreach ($error as $errorKey => $errorValue) {
                        $tempArray = [];
                        // from formbuilder validation: helps pinpoint an error
                        // hack due to bad implementation by previous developers
                        if ($key === 'question') {
                            $tempArray['code'] = 'question';
                            $tempArray['title'] = $errorValue;
                            $tempArray['source'] = 'question';
                        } else {
                            $tempArray['code'] = $errorValue;
                            // another ugly hack to get better error response (with parameters)
                            // does not translate if "ec5_" is not in the $errorValue string
                            // as it was already translated
                            if (!str_contains($errorValue, 'ec5_')) {
                                $tempArray['title'] = $errorValue;
                            } else {
                                $tempArray['title'] = config('epicollect.codes.' . $errorValue);
                            }
                            $tempArray['source'] = $key;
                        }
                        $parsedErrors[] = $tempArray;
                    }
                }
            }

            return new JsonResponse(
                ['errors' => $parsedErrors],
                $httpStatusCode,
                ['Content-Type' => 'application/vnd.api+json; charset=utf-8'],
                0
            );
        });
    }
}
