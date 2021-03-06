<?php

namespace BaseApiClient\Transport;

use Illuminate\Http\JsonResponse;
use BaseApiClient\HttpClient\Curl;
use Illuminate\Routing\UrlGenerator;
use BaseApiClient\Exceptions\ApiException;
use Illuminate\Http\Request as LaravelRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class Request
{
    /**
     * Host to make the calls to
     *
     * @var string
     */
    private $host;

    /**
     * Instance of the Http client class
     *
     * @var \BaseApiClient\HttpClient\Curl
     */
    private $httpClient;

    /**
     * Array with the headers from the last request
     *
     * @var array
     */
    private $headers;

    /**
     * Create a new request instance
     *
     * @param  string $host
     * @param  Curl   $httpClient
     */
    public function __construct($host, Curl $httpClient)
    {
        $this->host = $host;
        $this->httpClient = $httpClient;
    }

    /**
     * Add header to request.
     *
     * @param string $key
     * @param string $value
     */
    public function setHeader($key, $value)
    {
        $this->httpClient->setHeader($key, $value);
    }

    /**
     * Make a get request to the given endpoint
     *
     * @param  string $endpoint
     * @param  array  $parameters
     *
     * @return Response
     */
    public function get($endpoint, array $parameters = [])
    {
        $url = sprintf('%s%s?%s', $this->host, $endpoint, http_build_query($parameters));

        return $this->execute('GET', $url);
    }

    /**
     * Make a post request to the given endpoint
     *
     * @param  string $endpoint
     * @param  array  $parameters
     *
     * @return Response
     */
    public function post($endpoint, array $parameters = [])
    {
        return $this->execute('POST', sprintf('%s%s', $this->host, $endpoint), $parameters);
    }

    /**
     * Make a delete request to the given endpoint
     *
     * @param  string $endpoint
     * @param  array  $parameters
     *
     * @return Response
     */
    public function delete($endpoint, array $parameters = [])
    {
        return $this->execute('DELETE', sprintf('%s%s', $this->host, $endpoint), $parameters);
    }

    /**
     * Make an put request to the given endpoint
     *
     * @param  string $endpoint
     * @param  array  $parameters
     *
     * @return Response
     */
    public function put($endpoint, array $parameters = [])
    {
        return $this->execute('PUT', sprintf('%s%s', $this->host, $endpoint), $parameters);
    }

    /**
     * Make an patch request to the given endpoint
     *
     * @param  string $endpoint
     * @param  array  $parameters
     *
     * @return Response
     */
    public function patch($endpoint, array $parameters = [])
    {
        return $this->execute('PATCH', sprintf('%s%s', $this->host, $endpoint), $parameters);
    }

    /**
     * Return the headers from the last request
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Execute the http request
     *
     * @param  string $method
     * @param  string $url
     * @param  array  $parameters
     * @param  array  $headers
     *
     * @return Response
     *
     * @throws ApiException|HttpResponseException
     */
    public function execute($method, $url, array $parameters = [], $headers = [])
    {
        // Execute request and catch response
        list($response_data, $response_headers) = $this->httpClient->execute($method, $url, $parameters, $headers);

        // Check if we have a valid response
        if ($this->httpClient->hasErrors()) {
            throw new ApiException($this->httpClient->getErrors(), $this->httpClient->getHttpCode());
        }

        // Initiate the response
        $response = new Response($response_data, $this->httpClient);

        // Check the response code
        if ($response->getResponseCode() >= 400) {
            if ($response->getResponseCode() === 400) {
                $this->throwValidationException($response);
            }

            throw new ApiException($response->message, $response->getResponseCode());
        }

        // Set headers for later inspection
        $this->headers = $response_headers;

        // Return the response
        return $response;
    }

    /**
     * Throw the failed validation exception.
     *
     * @param  Response $response
     *
     * @throws HttpResponseException
     */
    protected function throwValidationException(Response $response)
    {
        // Get laravel request
        $request = app(LaravelRequest::class);

        // Translate errors
        $errors = $response->errors;
        foreach ($errors as $attr => $messages) {
            $errors[$attr] = array_map(function ($error) use ($attr) {
                return trans($error, ['attribute' => $attr]);
            }, $messages);
        }

        // Return response
        if ($request->ajax() || $request->wantsJson()) {
            $response = new JsonResponse([
                'message' => trans('validation.validation_failed'),
                'errors' => $errors,
            ], 400);
        }
        else {
            $response = redirect()->to($this->getRedirectUrl())
                ->withInput($request->input())
                ->withErrors($errors);
        }

        throw new HttpResponseException($response);
    }

    /**
     * Get the URL we should redirect to.
     *
     * @return string
     */
    protected function getRedirectUrl()
    {
        return app(UrlGenerator::class)->previous();
    }
}