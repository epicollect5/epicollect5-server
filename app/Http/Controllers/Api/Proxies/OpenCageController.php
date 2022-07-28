<?php

namespace ec5\Http\Controllers\Api\Proxies;

use ec5\Http\Controllers\Controller;
use GuzzleHttp\Client;

//forward geocoding by Open Cage Data (free trial, 2500 request per day, 1 req/sec)
//https://api.opencagedata.com/geocode/v1/json?q=PLACENAME&key=xxxxxxxxxx
//https://geocoder.opencagedata.com/api
class OpenCageController extends Controller
{
    public function fetchAPI($search)
    {
        $client = new Client();
        $url = env('OPENCAGE_ENDPOINT');
        $key = env('OPENCAGE_KEY');
        //build endpoint
        $url =  $url . '?q=' . $search . '&key=' . $key;

        $res = $client->request('GET', $url);
        //get response (as string)
        $response_data = $res->getBody()->getContents();

        //return JSON by setting header
        return response($response_data)->header('Content-Type', 'application/vnd.api+json;');
    }
}
