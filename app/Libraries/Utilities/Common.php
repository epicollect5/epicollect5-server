<?php

namespace ec5\Libraries\Utilities;

use Cookie;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

class Common
{
    public static function getPossibleAnswers($input): array
    {
        $possibleAnswers = $input['possible_answers'];
        $possibles = [];

        if (!empty($possibleAnswers)) {
            foreach ($possibleAnswers as $possibleAnswer) {
                $possibles[] = $possibleAnswer['answer_ref'];
            }
        }
        return $possibles;
    }

    public static function generateFilename($prefix, $f): string
    {
        /**
         *  Truncate anything bigger than 100 chars
         *  to keep the filename unique, a prefix is passed
         *
         *  We do this as we might have filename too long (i.e branch question of 255,
         *  then adding prefix we go over the max filename length (255)
         */

        return $prefix . '__' . Str::slug(substr(strtolower($f), 0, 100));
    }

    public static function isValidTimestamp($timestamp): bool
    {
        return ((string)(int)$timestamp === (string)$timestamp)
            && ($timestamp <= PHP_INT_MAX)
            && ($timestamp >= ~PHP_INT_MAX);
    }

    public static function formatBytes($bytes, $precision = 2): string
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        // Uncomment one of the following alternatives
        // $bytes /= pow(1024, $pow);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    public static function roundNumber($number, $round = 2)
    {
        $unit = ['', 'K', 'M', 'B', 'T'];

        if ($number === 0) {
            return 0;
        }

        if ($number < 10) {
            return $number;
        }

        if ($number < 1000) {
            return '≈ ' . ceil($number / 10) * 10;
        }

        return round($number / pow(1000, ($i = floor(log($number, 1000)))), $round) . $unit[$i];
    }

    public static function getTemplateHeaders($inputs, $selectedMapping, $mapTos): array
    {
        $bulkUploadables = array_keys(config('epicollect.strings.bulk_uploadables'));
        $csvHeaders = $mapTos;
        foreach ($inputs as $input) {
            $inputRef = $input['ref'];
            //only use question types bulk-uploadable
            if (in_array($input['type'], $bulkUploadables)) {
                //need to split location in its parts (no UTM for now)
                $mapTo = $selectedMapping[$inputRef]['map_to'];
                if ($input['type'] === config('epicollect.strings.inputs_type.location')) {
                    $csvHeaders[] = 'lat_' . $mapTo;
                    $csvHeaders[] = 'long_' . $mapTo;
                    $csvHeaders[] = 'accuracy_' . $mapTo;
                } else {
                    //if the input is a group, flatten the group inputs
                    if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                        foreach ($input['group'] as $groupInput) {
                            $groupInputRef = $groupInput['ref'];
                            if (in_array($groupInput['type'], $bulkUploadables)) {
                                $groupInputMapTo = $selectedMapping[$inputRef]['group'][$groupInputRef]['map_to'];
                                if ($groupInput['type'] === config('epicollect.strings.inputs_type.location')) {
                                    $csvHeaders[] = 'lat_' . $groupInputMapTo;
                                    $csvHeaders[] = 'long_' . $groupInputMapTo;
                                    $csvHeaders[] = 'accuracy_' . $groupInputMapTo;
                                } else {
                                    $csvHeaders[] = $groupInputMapTo;
                                }
                            }
                        }
                    } else {
                        $csvHeaders[] = $mapTo;
                    }
                }
            }
        }
        return $csvHeaders;
    }

    public static function replaceRefInStructure($existingRef, $newRef, $json)
    {
        $jsonAsStringSource = json_encode($json);
        // Replace the old ref with the new ref in the data
        $jsonAsStringDestination = str_replace($existingRef, $newRef, $jsonAsStringSource);
        return json_decode($jsonAsStringDestination, true);
    }

    public static function getLocationInputRefs($projectDefinition, $formIndex = 0): array
    {
        $locationInputRefs = [];
        $inputs = array_get($projectDefinition, 'data.project.forms.' . $formIndex . '.inputs');
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.location')) {
                $locationInputRefs[] = $input['ref'];
            }

            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.location')) {
                        $locationInputRefs[] = $groupInput['ref'];
                    }
                }
            }
        }

        return $locationInputRefs;
    }

    public static function getBranchLocationInputRefs($projectDefinition, $formIndex, $branchRef): array
    {
        $locationInputRefs = [];
        $inputs = array_get($projectDefinition, 'data.project.forms.' . $formIndex . '.inputs');
        foreach ($inputs as $input) {

            if ($input['type'] === config('epicollect.strings.inputs_type.branch') && $input['ref'] === $branchRef) {

                $branchInputs = $input['branch'];

                foreach ($branchInputs as $branchInput) {

                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.location')) {
                        $locationInputRefs[] = $branchInput['ref'];
                    }

                    if ($branchInput['type'] === config('epicollect.strings.inputs_type.group')) {
                        $groupInputs = $branchInput['group'];
                        foreach ($groupInputs as $groupInput) {
                            if ($groupInput['type'] === config('epicollect.strings.inputs_type.location')) {
                                $locationInputRefs[] = $groupInput['ref'];
                            }
                        }
                    }
                }
            }
        }

        return $locationInputRefs;
    }

    public static function getBranchRefs($projectDefinition, $formIndex = 0): array
    {
        $branchRefs = [];
        $inputs = array_get($projectDefinition, 'data.project.forms.' . $formIndex . '.inputs');
        foreach ($inputs as $input) {
            if ($input['type'] === config('epicollect.strings.inputs_type.branch')) {
                $branchRefs[] = $input['ref'];
            }

            if ($input['type'] === config('epicollect.strings.inputs_type.group')) {
                $groupInputs = $input['group'];
                foreach ($groupInputs as $groupInput) {
                    if ($groupInput['type'] === config('epicollect.strings.inputs_type.branch')) {
                        $branchRefs[] = $groupInput['ref'];
                    }
                }
            }
        }

        return $branchRefs;
    }

    public static function configWithParams($key, $params = [])
    {
        $value = config($key);
        foreach ($params as $placeholder => $replacement) {
            $value = str_replace(':' . $placeholder, $replacement, $value);
        }
        return $value;
    }

    //used to generate random Android device ID in tests
    public static function generateRandomHex($length = 16)
    {
        // Calculate the number of bytes needed
        $byteLength = (int)ceil($length / 2);

        // Generate random bytes
        $randomBytes = random_bytes($byteLength);

        // Convert random bytes to hexadecimal
        $hex = bin2hex($randomBytes);

        // Trim to desired length
        return substr($hex, 0, $length);
    }

    public static function getMonthName($monthNumber): string
    {
        return date("M", mktime(0, 0, 0, $monthNumber, 1));
    }

    //Use a cookie to signal the download has completed and hide overlay.
    //no need to be secured, just a timestamp
    public static function getMediaCookie($value)
    {
        $cookieName = config('epicollect.setup.cookies.download_entries');
        return Cookie::make(
            $cookieName,
            $value,
            //"If set to 0, or omitted, the cookie will expire at the end of the session (when the browser closes)."
            0,              // Duration in minutes
            '/',            // Path, use '/' to be accessible throughout the site
            null,           // Domain, use null for default
            false,           // Secure flag: should be true if using HTTPS
            false,          // HttpOnly: set to false for JavaScript access
            false,          // Raw: typically false unless raw encoding is needed
            'Lax'           // SameSite setting, using 'Lax' to avoid cross-site restrictions
        );
    }

    /**
     * Retrieves the running version of Epicollect5 from the CGPS production server.
     *
     * This method sends an HTTP GET request to a configured URL to fetch an HTML page containing
     * version information. It parses the response to extract the version number from a specific
     * element in the page footer. If the request fails or the version cannot be extracted, it returns
     * a default version ('0.0.0').
     *
     * @return string The Epicollect5 version number, or '0.0.0' if unavailable.
     */
    public static function getCGPSEpicollectVersion(): string
    {
        // Initialize Guzzle client
        $client = new Client();

        // Send a GET request to the URL
        $response = $client->get(config('epicollect.setup.cgps_epicollect_server_url'));

        // Check if the request was successful
        if ($response->getStatusCode() === 200) {
            // Get the HTML content of the page
            $html = (string)$response->getBody();

            $crawler = new Crawler($html);

            // Use the Crawler to locate the <small> element containing the version text
            $versionText = $crawler->filter('div.footer-links ul li small')->text();

            // Extract the version from the string
            preg_match('/v([\d.]+)/', $versionText, $matches);

            return $matches[1] ?? '0.0.0';
        }

        // Return if the request failed
        return '0.0.0';
    }

    /**
     * Generates an error response as a downloadable CSV file.
     *
     * This method is used during file downloads to ensure that any error is returned as a CSV file, keeping the user on the dataviewer page.
     * It sets a media cookie based on the provided timestamp, retrieves the error message using the supplied error code from the configuration,
     * and returns the error content formatted as a CSV file with the filename "epicollect5-error.txt".
     *
     * @param mixed $timestamp The timestamp used to generate a unique media cookie for the file download.
     * @param mixed $code The error code that corresponds to the error message found in the configuration.
     *
     * @return \Illuminate\Http\Response The CSV file response containing the error details.
     */
    public static function errorResponseAsFile($timestamp, $code)
    {
        // imp: this happens only when users are downloading the file, so send error as file
        // to keep the user on the dataviewer page.
        // because on the front end this is requested using window.location
        $mediaCookie = Common::getMediaCookie($timestamp);
        Cookie::queue($mediaCookie);
        $filename = 'epicollect5-error.txt';
        $content = config('epicollect.codes.'.$code);
        return Response::toCSVFile($content, $filename);
    }
}
