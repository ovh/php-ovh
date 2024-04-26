<?php

/*
 * Copyright (c) 2013-2024, OVH SAS.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in the
 *     documentation and/or other materials provided with the distribution.
 *   * Neither the name of OVH SAS nor the
 *     names of its contributors may be used to endorse or promote products
 *     derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY OVH SAS AND CONTRIBUTORS ``AS IS'' AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL OVH SAS AND CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

/**
 * This file contains code about \Ovh\Api class.
 */

namespace Ovh;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * Wrapper to manage login and exchanges with simpliest Ovh API.
 *
 * This class manage how works connections to the simple Ovh API with
 * login method and call wrapper
 * Http connections use guzzle http client api and result of request are
 * object from this http wrapper
 *
 * @category Ovh
 */
class Api
{
    /**
     * Url to communicate with Ovh API.
     *
     * @var array
     */
    private $endpoints = [
        'ovh-eu'        => 'https://eu.api.ovh.com/1.0',
        'ovh-ca'        => 'https://ca.api.ovh.com/1.0',
        'ovh-us'        => 'https://api.us.ovhcloud.com/1.0',
        'kimsufi-eu'    => 'https://eu.api.kimsufi.com/1.0',
        'kimsufi-ca'    => 'https://ca.api.kimsufi.com/1.0',
        'soyoustart-eu' => 'https://eu.api.soyoustart.com/1.0',
        'soyoustart-ca' => 'https://ca.api.soyoustart.com/1.0',
        'runabove-ca'   => 'https://api.runabove.com/1.0',
    ];

    /**
     * Contain endpoint selected to choose API.
     */
    private ?string $endpoint;

    /**
     * Contain key of the current application.
     */
    private ?string $application_key;

    /**
     * Contain secret of the current application.
     */
    private ?string $application_secret;

    /**
     * Contain consumer key of the current application.
     */
    private ?string $consumer_key;

    /**
     * Contain delta between local timestamp and api server timestamp.
     */
    private ?string $time_delta;

    /**
     * Contain http client connection.
     */
    private ?Client $http_client;

    /**
     * Construct a new wrapper instance.
     *
     * @param string $application_key    key of your application.
     *                                   For OVH APIs, you can create a application's credentials on
     *                                   https://api.ovh.com/createApp/
     * @param string $application_secret secret of your application
     * @param string $api_endpoint       name of api selected
     * @param string $consumer_key       If you have already a consumer key, this parameter prevent to do a
     *                                   new authentication
     * @param Client $http_client        instance of http client
     *
     * @throws Exceptions\InvalidParameterException if one parameter is missing or with bad value
     */
    public function __construct(
        $application_key,
        $application_secret,
        $api_endpoint,
        $consumer_key = null,
        ?Client $http_client = null
    ) {
        if (!isset($api_endpoint)) {
            throw new Exceptions\InvalidParameterException('Endpoint parameter is empty');
        }

        if (preg_match('/^https?:\/\/..*/', $api_endpoint)) {
            $this->endpoint = $api_endpoint;
        } else {
            if (!array_key_exists($api_endpoint, $this->endpoints)) {
                throw new Exceptions\InvalidParameterException('Unknown provided endpoint');
            }

            $this->endpoint = $this->endpoints[$api_endpoint];
        }

        if (!isset($http_client)) {
            $http_client = new Client([
                'timeout'         => 30,
                'connect_timeout' => 5,
            ]);
        }

        $this->application_key    = $application_key;
        $this->application_secret = $application_secret;
        $this->http_client        = $http_client;
        $this->consumer_key       = $consumer_key;
    }

    /**
     * Request a consumer key from the API and the validation link to
     * authorize user to validate this consumer key.
     *
     * @param array  $accessRules list of rules your application need
     * @param string $redirection url to redirect on your website after authentication
     *
     * @return mixed
     *
     * @throws ClientException if http request is an error
     */
    public function requestCredentials(
        array $accessRules,
        $redirection = null
    ) {
        $parameters              = new \stdClass();
        $parameters->accessRules = $accessRules;
        $parameters->redirection = $redirection;

        // bypass authentication for this call
        $response = $this->decodeResponse(
            $this->rawCall(
                'POST',
                '/auth/credential',
                $parameters,
                true
            )
        );

        $this->consumer_key = $response['consumerKey'];

        return $response;
    }

    /**
     * Wrap call to Ovh APIs for GET requests.
     *
     * @param string $path    path ask inside api
     * @param array  $content content to send inside body of request
     * @param array  headers  custom HTTP headers to add on the request
     * @param bool   is_authenticated   if the request need to be authenticated
     * @param null|mixed $headers
     * @param mixed      $is_authenticated
     *
     * @throws ClientException if http request is an error
     * @throws \JsonException
     */
    public function get($path, $content = null, $headers = null, $is_authenticated = true)
    {
        if (preg_match('/^\/[^\/]+\.json$/', $path)) {
            // Schema description must be access without authentication
            return $this->decodeResponse(
                $this->rawCall('GET', $path, $content, false, $headers)
            );
        }

        return $this->decodeResponse(
            $this->rawCall('GET', $path, $content, $is_authenticated, $headers)
        );
    }

    /**
     * Wrap call to Ovh APIs for POST requests.
     *
     * @param string $path    path ask inside api
     * @param array  $content content to send inside body of request
     * @param array  headers  custom HTTP headers to add on the request
     * @param bool   is_authenticated   if the request need to be authenticated
     * @param null|mixed $headers
     * @param mixed      $is_authenticated
     *
     * @throws ClientException if http request is an error
     */
    public function post($path, $content = null, $headers = null, $is_authenticated = true)
    {
        return $this->decodeResponse(
            $this->rawCall('POST', $path, $content, $is_authenticated, $headers)
        );
    }

    /**
     * Wrap call to Ovh APIs for PUT requests.
     *
     * @param string $path    path ask inside api
     * @param array  $content content to send inside body of request
     * @param array  headers  custom HTTP headers to add on the request
     * @param bool   is_authenticated   if the request need to be authenticated
     * @param null|mixed $headers
     * @param mixed      $is_authenticated
     *
     * @throws ClientException if http request is an error
     */
    public function put($path, $content, $headers = null, $is_authenticated = true)
    {
        return $this->decodeResponse(
            $this->rawCall('PUT', $path, $content, $is_authenticated, $headers)
        );
    }

    /**
     * Wrap call to Ovh APIs for DELETE requests.
     *
     * @param string $path    path ask inside api
     * @param array  $content content to send inside body of request
     * @param array  headers  custom HTTP headers to add on the request
     * @param bool   is_authenticated   if the request need to be authenticated
     * @param null|mixed $headers
     * @param mixed      $is_authenticated
     *
     * @throws ClientException if http request is an error
     */
    public function delete($path, $content = null, $headers = null, $is_authenticated = true)
    {
        return $this->decodeResponse(
            $this->rawCall('DELETE', $path, $content, $is_authenticated, $headers)
        );
    }

    /**
     * Get the current consumer key.
     */
    public function getConsumerKey(): ?string
    {
        return $this->consumer_key;
    }

    /**
     * Return instance of http client.
     */
    public function getHttpClient(): ?Client
    {
        return $this->http_client;
    }

    /**
     * getTarget returns the URL to target given an endpoint and a path.
     * If the path starts with `/v1` or `/v2`, then remove the trailing `/1.0` from the endpoint.
     *
     * @param string path to use prefix from
     * @param mixed $path
     */
    protected function getTarget($path): string
    {
        $endpoint = $this->endpoint;
        if ('/1.0' == substr($endpoint, -4) && (
            '/v1' == substr($path, 0, 3)
                || '/v2' == substr($path, 0, 3)
        )) {
            $endpoint = substr($endpoint, 0, strlen($endpoint) - 4);
        }

        return $endpoint . $path;
    }

    /**
     * This is the main method of this wrapper. It will
     * sign a given query and return its result.
     *
     * @param string               $method           HTTP method of request (GET,POST,PUT,DELETE)
     * @param string               $path             relative url of API request
     * @param null|array|\stdClass $content          body of the request
     * @param bool                 $is_authenticated if the request use authentication
     * @param null                 $headers
     *
     * @throws Exceptions\InvalidParameterException
     * @throws GuzzleException
     * @throws \JsonException
     */
    protected function rawCall($method, $path, $content = null, $is_authenticated = true, $headers = null): ResponseInterface
    {
        if ($is_authenticated) {
            if (!isset($this->application_key)) {
                throw new Exceptions\InvalidParameterException('Application key parameter is empty');
            }

            if (!isset($this->application_secret)) {
                throw new Exceptions\InvalidParameterException('Application secret parameter is empty');
            }
        }

        $url     = $this->getTarget($path);
        $request = new Request($method, $url);
        if (isset($content) && 'GET' === $method) {
            $query_string = $request->getUri()->getQuery();

            $query = [];
            if (!empty($query_string)) {
                $queries = explode('&', $query_string);
                foreach ($queries as $element) {
                    $key_value_query            = explode('=', $element, 2);
                    $query[$key_value_query[0]] = $key_value_query[1];
                }
            }

            $query = array_merge($query, (array) $content);

            // rewrite query args to properly dump true/false parameters
            foreach ($query as $key => $value) {
                if (false === $value) {
                    $query[$key] = 'false';
                } elseif (true === $value) {
                    $query[$key] = 'true';
                }
            }

            $query = Query::build($query);

            $url     = $request->getUri()->withQuery($query);
            $request = $request->withUri($url);
            $body    = '';
        } elseif (isset($content)) {
            $body = json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

            $request->getBody()->write($body);
        } else {
            $body = '';
        }
        if (!is_array($headers)) {
            $headers = [];
        }
        $headers['Content-Type'] = 'application/json; charset=utf-8';

        $headers['X-Ovh-Application'] = $this->application_key ?? '';
        if ($is_authenticated) {
            if (!isset($this->time_delta)) {
                $this->calculateTimeDelta();
            }
            $now = time() + $this->time_delta;

            $headers['X-Ovh-Timestamp'] = $now;

            if (isset($this->consumer_key)) {
                $toSign = $this->application_secret . '+' . $this->consumer_key . '+' . $method
                    . '+' . $url . '+' . $body . '+' . $now;
                $signature                  = '$1$' . sha1($toSign);
                $headers['X-Ovh-Consumer']  = $this->consumer_key;
                $headers['X-Ovh-Signature'] = $signature;
            }
        }

        /* @var Response $response */
        return $this->http_client->send($request, ['headers' => $headers]);
    }

    /**
     * Calculate time delta between local machine and API's server.
     *
     * @return int
     *
     * @throws ClientException if http request is an error
     */
    private function calculateTimeDelta()
    {
        if (!isset($this->time_delta)) {
            $response = $this->rawCall(
                'GET',
                '/auth/time',
                null,
                false
            );
            $serverTimestamp  = (int) (string) $response->getBody();
            $this->time_delta = $serverTimestamp - (int) \time();
        }

        return $this->time_delta;
    }

    /**
     * Decode a Response object body to an Array.
     *
     * @throws \JsonException
     */
    private function decodeResponse(Response $response)
    {
        if (204 === $response->getStatusCode() || 0 === $response->getBody()->getSize()) {
            return null;
        }

        return json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
