<?php

namespace ec5\Http\Controllers\Api;

use ec5\Libraries\Utilities\Arrays;
use ec5\Libraries\Utilities\Strings;
use Request;
use Log;
use Exception;

/**
 * Class ApiRequest
 * @package ec5\Http\Controllers\Api
 *
 * Please note that this class might not reflect the most optimal code practices.
 * It was developed by a less experienced team member, and due to time constraints,
 * I'm currently working on patching and improving specific sections rather than a complete rewrite.
 * Apologies for any inconvenience this might cause.
 */
class ApiRequest
{

    /**
     * Contains the url of the request
     *
     * @var string
     */
    public $url;

    /**
     * The type of the resource ie project, entry
     *
     * @var
     */
    protected $type;

    /**
     * Contains the HTTP method of the request
     *
     * @var string
     */
    public $method;

    /**
     * Contains an optional model ID from the request
     *
     * @var int
     */
    public $id;

    /**
     * Contains any content in request
     *
     * @var string
     */
    public $meta;


    /**
     * The data payload
     *
     * @var
     */
    public $data = [];

    /**
     * @var
     */
    public $content;

    /**
     * Contains an array of linked resource collections to load
     *
     * @var array
     */
    public $included;

    /**
     * Contains an array of column names to sort on
     *
     * @var array
     */
    public $sort;

    /**
     * Contains an array of key/value pairs to filter on
     *
     * @var array
     */
    public $filter;

    /**
     * Specifies the page number to return results for
     * @var integer
     */
    public $pageNumber;

    /**
     * Pagination
     *
     * @var integer
     */
    public $page;

    /**
     * Specifies the number of results to return per page. Only used if
     * pagination is requested (ie. pageNumber is not null)
     *
     * @var integer
     */
    public $pageSize = 50;

    /**
     * Errors array
     *
     * @var array
     */
    protected $errors = array();

    /**
     * The 'type' data ie [data][project], []
     *
     * @var array
     */
    protected $typeData = array();

    /**
     * Relationships
     *
     * @var
     */
    public $relationships;

    /**
     * Attributes
     *
     * @var
     */
    public $attributes;

    /**
     * Constructor.
     *
     *
     */
    public function __construct()
    {
        $this->url = Request::url();
        $this->method = Request::method();
        if (Request::input('meta')) {
            $this->meta = Request::input('meta');
        }
        if (Request::input('data')) {
            $this->data = Request::input('data');
        }

        try {
            // If we have a multipart request, the JSON will not be an object, but a string
            if (preg_match('/multipart\/form\-data/', Request::header('Content-Type'))) {
                // Parse JSON string data to array
                $this->data = json_decode($this->data, true);
            }
        } catch (Exception $e) {

            //todo does it fail only on slow networks?
            $request = Request::instance();

            Log::error('multipart request try/catch failed: ', [
                'url' => $this->url,
                'method' => $this->method,
                'meta' => $this->meta ?? '',
                'data' => $this->data ?? '',
                'content' => $request->all()
            ]);
            $this->errors['File was not sent, missing on device.'] = ['ec5_231'];
            return;
        }

        // Check the data is valid
        if (!$this->isValidData()) {
            return;
        }

        $this->included = ($i = Request::input('included')) ? explode(',', $i) : $i;
        $this->sort = ($i = Request::input('sort')) ? explode(',', $i) : $i;
        $this->filter = ($i = Request::except(['data', 'meta', 'sort', 'included', 'page'])) ? $i : [];

        $this->page = Request::input('page');
        $this->pageSize = null;
        $this->pageNumber = null;

        $this->parseRequestContent();

    }

    /**
     * Check if the data is valid
     *
     * @return bool
     */
    private function isValidData(): bool
    {
        // If JSON not correctly formatted and it is a POST request
        if (!$this->data && $this->method === 'POST') {
            $this->errors['json-data-tag'] = ['ec5_14'];

            Log::error('Missing JSON "data" key in request: ', [
                'url' => $this->url,
                'method' => $this->method,
                'meta' => $this->meta ?? '',
                'data' => $this->data ?? '',
                'content' => Request::getContent()
            ]);
            return false;
        }
        // Implode as string so we can use regex
        $stringData = Arrays::implodeMulti($this->data);

        // If preg_match returns 1 or false, error out
        // 0 means no matches, which is the only case allowed

        // Check for HTML
        if (Strings::containsHtml($stringData)) {
            $this->errors['json-contains-html'] = ['ec5_220'];
            return false;
        }

        // Check for emoji
        if (Strings::containsEmoji($stringData)) {
            $this->errors['json-contains-emoji'] = ['ec5_323'];
            return false;
        }

        return true;

    }

    /**
     * Determine if there were any errors
     *
     * @return bool
     */
    public function hasErrors()
    {
        return count($this->errors) > 0;
    }

    /**
     * Return all errors
     *
     * @return array
     */
    public function errors()
    {
        return $this->errors;
    }

    /**
     * Return the 'data' array
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Return the 'type' 'data' array
     * @return array
     */
    public function getTypeData()
    {
        return ($this->type != null && isset($this->data[$this->type])) ? $this->data[$this->type] : [];
    }

    /**
     * Return any relationships
     *
     * @return mixed
     */
    public function getRelationships()
    {
        return $this->relationships;
    }

    /**
     * Return any attributes
     *
     * @return mixed
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @param $type
     * @return bool
     */
    public function hasType($type)
    {
        return $this->type == $type;
    }

    /**
     * Return the 'content' array
     *
     * @return mixed
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Parses json_decode content from request by type ie data[$type] into an array of values.
     * set $this->api_data_by_type
     */
    protected function parseRequestContent()
    {

        if (count($this->data) == 0 && $this->method === 'POST') {
            $this->errors['json-data-tag'] = ['ec5_14'];
            return;
        }

        // Set the type attribute
        $this->type = (!empty($this->data['type']) ? $this->data['type'] : null);

        if (($this->type == null || empty($this->data[$this->type])) && $this->method === 'POST') {
            $this->errors['json-data-tag'] = ['ec5_14'];
            return;
        }

        $this->attributes = (!empty($this->data['attributes']) ? $this->data['attributes'] : []);

        $relationships = (!empty($this->data['relationships']) ? $this->data['relationships'] : []);

        // Parse through relationships, pulling out 'data'
        foreach ($relationships as $key => $value) {
            $data = (!empty($value['data']) ? $value['data'] : []);
            $this->relationships[$key] = $data;
        }
    }
}
