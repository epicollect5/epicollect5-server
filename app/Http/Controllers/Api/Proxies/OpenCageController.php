<?php

namespace ec5\Http\Controllers\Api\Proxies;

use ec5\Http\Controllers\Controller;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

//forward geocoding by Open Cage Data (free trial, 2500 request per day, 1 req/sec)
//https://api.opencagedata.com/geocode/v1/json?q=PLACENAME&key=xxxxxxxxxx
//https://geocoder.opencagedata.com/api
class OpenCageController extends Controller
{
    /**
     * @throws GuzzleException
     */
    public function fetchAPI($search)
    {
        $client = new Client();
        $url = config('epicollect.setup.opencage.endpoint');
        $key = config('epicollect.setup.opencage.key');
        //build endpoint
        $url = $url . '?q=' . $search . '&key=' . $key;

        $res = $client->request('GET', $url);
        //get response (as string)
        $response_data = $res->getBody()->getContents();

        //return JSON by setting header
        return response($response_data)->header('Content-Type', 'application/vnd.api+json;');
    }
}
