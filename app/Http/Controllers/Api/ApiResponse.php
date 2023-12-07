<?php

namespace ec5\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

use Lang;

/**
 *
 */
class ApiResponse
{
    /**
     * An array of parameters.
     *
     * @var array
     */
    protected $responseData = [];

    /**
     * @var array
     */
    protected $data = [];

    /**
     * @var array
     */
    protected $meta = [];

    /**
     * @var array
     */
    protected $links = [];

    /**
     * @var array
     */
    protected $included = [];

    /**
     * HTTP status code
     *
     * @var int
     */
    protected $httpStatusCode;

    /**
     * here we set keys ie relationships, links etc BUT IF  == 'body'
     * the type like project or entries will be set to $this->body var
     *
     * @param string $key
     * @param mixed $value
     */
    public function __set($key, $value)
    {
        if ($key == 'body') {
            $this->data = $value;
            return;
        }
        $this->responseData[$key] = $value;
    }

    /**
     * Set the response data into the body
     *
     * @param $data
     */
    public function setData($data)
    {
        $this->data['data'] = $data;
    }

    /**
     * Set the response meta into the body
     *
     * @param $meta
     */
    public function setMeta($meta)
    {
        $this->meta['meta'] = $meta;
    }

    /**
     * Set the response links into the body
     *
     * @param $links
     */
    public function setLinks($links)
    {
        $this->links['links'] = $links;
    }

    /**
     * Set the response included into the body
     *
     * @param $included
     */
    public function setIncluded($included)
    {
        $this->included['included'] = $included;
    }

    /**
     * Returns the JsonResponse.
     */
    public function toJsonResponse($httpStatusCode, $options = JSON_UNESCAPED_SLASHES, $additionalHeaders = [])
    {
        $data = $this->data;
        // Do we have meta?
        if (count($this->meta) > 0) {
            $data = array_merge($this->meta, $data);
        }
        // Do we have links?
        if (count($this->links) > 0) {
            $data = array_merge($this->links, $data);
        }
        // Do we have included?
        if (count($this->included) > 0) {
            $data = array_merge($this->included, $data);
        }

        return new JsonResponse($data, $httpStatusCode, array_merge(['Content-Type' => 'application/vnd.api+json; charset=utf-8'], $additionalHeaders), $options);
    }

    /**
     * Returns the CsvResponse.
     *
     * @param $data
     */
    public function toCsvResponse($data)
    {

        header('Content-Type: application/csv');
        fpassthru($data);

    }

    /**
     * Returns the Json success response
     *
     * @param $code
     * @return JsonResponse
     */
    public function successResponse($code)
    {
        $this->setData([
            'code' => $code,
            'title' => trans('status_codes.' . $code)
        ]);

        return $this->toJsonResponse(200);
    }

    /**
     * Returns the Json error response
     *
     * @param $httpStatusCode
     * @param array $errors
     * @param array $extra
     * @return JsonResponse
     */
    public function errorResponse($httpStatusCode, array $errors, array $extra = array())
    {
        // out array
        $outArray = [];

        // loop though $errors and format into api error array
        foreach ($errors as $key => $error) {

            // temp array to store error, expecting array otherwise skip
            if (is_array($error)) {

                foreach ($error as $errorKey => $errorValue) {

                    $tempArray = [];

                    //from formbuilder validation: helps pin-point an error
                    //hack due to bad implementation by previous developers
                    if ($key === 'question') {
                        $tempArray['code'] = 'question';
                        $tempArray['title'] = $errorValue;
                        $tempArray['source'] = 'question';
                    } else {
                        $tempArray['code'] = $errorValue;


                        //another ugly hack to get better error response (with parameters)
                        //does not translate if "ec5_" is not in the $errorValue string
                        //as it was already translated
                        if (strpos($errorValue, 'ec5_') === false) {
                            $tempArray['title'] = $errorValue;
                        } else {
                            $tempArray['title'] = Lang::get('status_codes.' . $errorValue);

                        }
                        $tempArray['source'] = $key;
                    }

                    // add temp error array to out array
                    array_push($outArray, $tempArray);
                }
            }
        }

        return new JsonResponse(array_merge(
                ['errors' => $outArray], $extra)
            , $httpStatusCode, ['Content-Type' => 'application/vnd.api+json; charset=utf-8'], $options = 0);

    }
}
