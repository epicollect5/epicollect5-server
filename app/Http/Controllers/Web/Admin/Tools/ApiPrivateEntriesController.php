<?php

namespace ec5\Http\Controllers\Web\Admin\Tools;

use ec5\Http\Controllers\Controller;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client;

class ApiPrivateEntriesController extends Controller
{
    private $serverURL;
    private $clientId;
    private $clientSecret;
    private $projectSlug;
    private $mediaEndpoint;

    public function __construct()
    {
        $this->serverURL = 'https://dev.epicollect.net';
        $this->clientId = '80';
        $this->clientSecret = 'SPeAldxewNZuIjAI9lXK2UtmnguzkWwIwicuhSc4';
        $this->projectSlug = 'ec5-api-test';
        $this->mediaEndpoint = '/api/export/media/';

    }

    //get entries for a private project to test the response
    public function getEntries()
    {
        $tokenClient = new Client();
        //can expose localhost using ngrok if needed
        $tokenURL = $this->serverURL . '/api/oauth/token';

        //get token first
        try {
            $tokenResponse = $tokenClient->request('POST', $tokenURL, [
                'headers' => ['Content-Type' => 'application/vnd.api+json'],
                'body' => json_encode([
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret
                ])
            ]);

            $body = $tokenResponse->getBody();
            $obj = json_decode($body);
            $token = $obj->access_token;
        } catch (RequestException $e) {
            //handle errors
            echo $e->getMessage();
            exit();

        }

        //get entries now
        $entriesURL = $this->serverURL . '/api/export/entries/' . $this->projectSlug;
//        $childEntriesURL = $this->serverURL.'/api/export/entries/'.$projectSlug.' ?map_index=&form_ref=343196b968c5408eab5979bace15c850_5984724af75be';
//        $parentEntryUuid = '4492790c-443c-256a-d7c2-7321b96e7ee6';
//        $parentFormRef = '343196b968c5408eab5979bace15c850_59819b13f1d3c';
//        $branchRef = '343196b968c5408eab5979bace15c850_59819b13f1d3c_598451783a61a';
//        $branchEntriesURL = $serverURL.'/api/export/entries/'.$projectSlug.'?branch_ref='.$branchRef;
//        $branchOwneruuid = '4492790c-443c-256a-d7c2-7321b96e7ee6';


        $entriesClient = new Client([
            'headers' => [
                'Authorization' => 'Bearer ' . $token //this will last for 2 hours!
            ]
        ]);

        try {
            //Get all entries for main form
            $response = $entriesClient->request('GET', $entriesURL);

            //get all branch entries
            // $response = $entriesClient->request('GET', $branchEntriesURL);

            //get all branch entries for a main entry
            // $response = $entriesClient->request('GET', $branchEntriesURL.'&branch_owner_uuid='.$branchOwneruuid);

            //get all child form entries
            //  $response = $entriesClient->request('GET', $childEntriesURL);


            //get child form entry for a parent entry
            // $response = $entriesClient->request('GET', $childEntriesURL.'&parent_uuid='.$parentEntryUuid.'&parent_form_ref='.$parentFormRef.'&map_index=1');

            $body = $response->getBody();
            $obj = json_decode($body);

            $entries = $obj->data->entries;

            //do something with the entries
            echo '<pre>';
            print_r($entries);
            echo '</pre>';

        } catch (RequestException $e) {
            //handle errors
            echo $e->getMessage();
            exit();
        }
    }

    public function getMedia()
    {

        $tokenClient = new Client();
        //can expose localhost using ngrok if needed
        $tokenURL = $this->serverURL . '/api/oauth/token';

        //get token first
        try {
            $tokenResponse = $tokenClient->request('POST', $tokenURL, [
                'headers' => ['Content-Type' => 'application/vnd.api+json'],
                'body' => json_encode([
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret
                ])
            ]);

            $body = $tokenResponse->getBody();
            $obj = json_decode($body);
            $token = $obj->access_token;
        } catch (RequestException $e) {
            //handle errors
            echo $e->getMessage();
            exit();

        }

        //get entries now
        $entriesURL = $this->serverURL . '/api/export/entries/' . $this->projectSlug;
//        $childEntriesURL = $this->serverURL.'/api/export/entries/'.$projectSlug.' ?map_index=&form_ref=343196b968c5408eab5979bace15c850_5984724af75be';
//        $parentEntryUuid = '4492790c-443c-256a-d7c2-7321b96e7ee6';
//        $parentFormRef = '343196b968c5408eab5979bace15c850_59819b13f1d3c';
//        $branchRef = '343196b968c5408eab5979bace15c850_59819b13f1d3c_598451783a61a';
//        $branchEntriesURL = $serverURL.'/api/export/entries/'.$projectSlug.'?branch_ref='.$branchRef;
//        $branchOwneruuid = '4492790c-443c-256a-d7c2-7321b96e7ee6';


        $entriesClient = new Client([
            'headers' => [
                'Authorization' => 'Bearer ' . $token //this will last for 2 hours!
            ]
        ]);

        try {
            //Get all entries for main form
            $response = $entriesClient->request('GET', $entriesURL);

            //get all branch entries
            // $response = $entriesClient->request('GET', $branchEntriesURL);

            //get all branch entries for a main entry
            // $response = $entriesClient->request('GET', $branchEntriesURL.'&branch_owner_uuid='.$branchOwneruuid);

            //get all child form entries
            //  $response = $entriesClient->request('GET', $childEntriesURL);


            //get child form entry for a parent entry
            // $response = $entriesClient->request('GET', $childEntriesURL.'&parent_uuid='.$parentEntryUuid.'&parent_form_ref='.$parentFormRef.'&map_index=1');

            $body = $response->getBody();
            $obj = json_decode($body);

            $entries = $obj->data->entries;


            foreach ($entries as $entry) {

                //set headers to force the browser to download the image
                //header('Content-Description: File Transfer');
                //  header('Content-Type: application/octet-stream');
                //  header('Content-Disposition: attachment; filename="' . $entry->photo . '"');

                //build the full resolution image url
                $photoURL = $this->serverURL . $this->mediaEndpoint . $this->projectSlug . '?type=photo&format=entry_original&name=' . $entry->photo;

                //Get file
                $response = $entriesClient->request('GET', $photoURL);

                //decode image
                $imageData = imagecreatefromstring(($response->getBody()));

                $b64image = base64_encode($response->getBody());

                $entriesClient->request('GET', $photoURL, ['sink' => storage_path() . '/' . $entry->photo]);

                echo '<img src="data:image/png;base64,' . $b64image . '" />';
                echo '<br/>';

                //download
                //  echo $response->getBody();

            }


        } catch (RequestException $e) {
            //handle errors
            echo $e->getMessage();
            exit();
        }
    }
}
