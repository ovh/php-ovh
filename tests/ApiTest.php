<?php
# Copyright (c) 2013-2023, OVH SAS.
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions are met:
#
#   * Redistributions of source code must retain the above copyright
#     notice, this list of conditions and the following disclaimer.
#   * Redistributions in binary form must reproduce the above copyright
#     notice, this list of conditions and the following disclaimer in the
#     documentation and/or other materials provided with the distribution.
#   * Neither the name of OVH SAS nor the
#     names of its contributors may be used to endorse or promote products
#     derived from this software without specific prior written permission.
#
# THIS SOFTWARE IS PROVIDED BY OVH SAS AND CONTRIBUTORS ``AS IS'' AND ANY
# EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
# WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
# DISCLAIMED. IN NO EVENT SHALL OVH SAS AND CONTRIBUTORS BE LIABLE FOR ANY
# DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
# (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
# LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
# ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
# (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
# SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

namespace Ovh\tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use Ovh\Api;
use Ovh\Exceptions\InvalidParameterException;
use PHPUnit\Framework\TestCase;

# Mock values
const MOCK_APPLICATION_KEY = "TDPKJdwZwAQPwKX2";
const MOCK_APPLICATION_SECRET = "9ufkBmLaTQ9nz5yMUlg79taH0GNnzDjk";
const MOCK_CONSUMER_KEY = "5mBuy6SUQcRw2ZUxg0cG68BoDKpED4KY";
const MOCK_TIME = '1457018875';

class MockClient extends Client
{
    public $calls = [];

    public function __construct(...$responses)
    {

        $mock = new MockHandler($responses);
        $history = Middleware::history($this->calls);

        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push($history);

        parent::__construct(['handler' => $handlerStack]);
    }
}

/**
 * Test Api class
 *
 * @package  Ovh
 * @category Ovh
 */
class ApiTest extends TestCase
{
    /**
     * Get private and protected property to unit test it
     *
     * @param string $name
     *
     * @return \ReflectionProperty
     */
    protected static function getPrivateProperty($name)
    {
        $class    = new \ReflectionClass('Ovh\Api');
        $property = $class->getProperty($name);
        $property->setAccessible(true);

        return $property;
    }

    /**
     * Test missing $application_key
     */
    public function testMissingApplicationKey()
    {
        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Application key parameter is empty');
        $api = new Api(null, MOCK_APPLICATION_SECRET, 'ovh-eu', MOCK_CONSUMER_KEY, new MockClient());
        $api->get('/me');
    }

    /**
     * Test missing $application_secret
     */
    public function testMissingApplicationSecret()
    {
        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Application secret parameter is empty');
        $api = new Api(MOCK_APPLICATION_KEY, null, 'ovh-eu', MOCK_CONSUMER_KEY, new MockClient());
        $api->get('/me');
    }

    /**
     * Test we don't check Application Key for unauthenticated call
     */
    public function testNoCheckAppKeyForUnauthCall()
    {
        $client = new MockClient(new Response(200, [], '{}'));

        $api = new Api(null, null, 'ovh-eu', null, $client);
        $api->get("/me", null, null, false);

        $calls = $client->calls;
        $this->assertCount(1, $calls);

        $req = $calls[0]['request'];
        $this->assertSame('GET', $req->getMethod());
        $this->assertSame('https://eu.api.ovh.com/1.0/me', $req->getUri()->__toString());
        $this->assertSame('', $req->getHeaderLine('X-Ovh-Application'));
    }

    /**
     * Test missing $api_endpoint
     */
    public function testMissingApiEndpoint()
    {
        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Endpoint parameter is empty');
        new Api(MOCK_APPLICATION_KEY, MOCK_APPLICATION_SECRET, null, MOCK_CONSUMER_KEY, new MockClient());
    }

    /**
     * Test bad $api_endpoint
     */
    public function testBadApiEndpoint()
    {
        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Unknown provided endpoint');
        new Api(MOCK_APPLICATION_KEY, MOCK_APPLICATION_SECRET, 'i_am_invalid', MOCK_CONSUMER_KEY, new MockClient());
    }

    /**
     * Test creating Client if none is provided
     */
    public function testClientCreation()
    {
        $api = new Api(MOCK_APPLICATION_KEY, MOCK_APPLICATION_SECRET, 'ovh-eu', MOCK_CONSUMER_KEY);
        $this->assertInstanceOf('\\GuzzleHttp\\Client', $api->getHttpClient());
    }

    /**
     * Test the compute of time delta
     */
    public function testTimeDeltaCompute()
    {
        $client = new MockClient(
            new Response(200, [], time() - 10),
            new Response(200, [], '{}'),
        );

        $api = new Api(MOCK_APPLICATION_KEY, MOCK_APPLICATION_SECRET, 'ovh-eu', MOCK_CONSUMER_KEY, $client);
        $api->get("/me");

        $property = self::getPrivateProperty('time_delta');
        $time_delta = $property->getValue($api);
        $this->assertSame('-10', $time_delta);

        $calls = $client->calls;
        $this->assertCount(2, $calls);

        $req = $calls[0]['request'];
        $this->assertSame('GET', $req->getMethod());
        $this->assertSame('https://eu.api.ovh.com/1.0/auth/time', $req->getUri()->__toString());

        $req = $calls[1]['request'];
        $this->assertSame('GET', $req->getMethod());
        $this->assertSame('https://eu.api.ovh.com/1.0/me', $req->getUri()->__toString());
    }

    /**
     * Test if consumer key is replaced
     */
    public function testIfConsumerKeyIsReplace()
    {
        $client = new MockClient(
            new Response(200, [], MOCK_TIME),
            new Response(
                200,
                ['Content-Type' => 'application/json; charset=utf-8'],
                '{"validationUrl":"https://api.ovh.com/login/?credentialToken=token","consumerKey":"consumer_remote","state":"pendingValidation"}'
            ),
        );

        $api = new Api(MOCK_APPLICATION_KEY, MOCK_APPLICATION_SECRET, 'ovh-eu', MOCK_CONSUMER_KEY, $client);
        $this->assertNotEquals('consumer_remote', $api->getConsumerKey());
        $credentials = $api->requestCredentials(['method' => 'GET', 'path' => '/*']);
        $this->assertSame('consumer_remote', $api->getConsumerKey());

        $calls = $client->calls;
        $this->assertCount(2, $calls);

        $req = $calls[0]['request'];
        $this->assertSame('GET', $req->getMethod());
        $this->assertSame('https://eu.api.ovh.com/1.0/auth/time', $req->getUri()->__toString());

        $req = $calls[1]['request'];
        $this->assertSame('POST', $req->getMethod());
        $this->assertSame('https://eu.api.ovh.com/1.0/auth/credential', $req->getUri()->__toString());
    }

    /**
     * Test invalid applicationKey
     */
    public function testInvalidApplicationKey()
    {
        $error = '{"class":"Client::Forbidden","message":"Invalid application key"}';
        $this->expectException(ClientException::class);
        $this->expectExceptionCode(403);
        $this->expectExceptionMessage($error);

        $client = new MockClient(new Response(403, ['Content-Type' => 'application/json; charset=utf-8'], $error));

        $api = new Api(MOCK_APPLICATION_KEY, MOCK_APPLICATION_SECRET, 'ovh-eu', MOCK_CONSUMER_KEY, $client);
        $api->requestCredentials(['method' => 'GET', 'path' => '/*']);
    }

    /**
     * Test invalid rights
     */
    public function testInvalidRight()
    {
        $error = '{"message": "Invalid credentials"}';
        $this->expectException(ClientException::class);
        $this->expectExceptionCode(403);
        $this->expectExceptionMessage($error);

        $client = new MockClient(new Response(403, ['Content-Type' => 'application/json; charset=utf-8'], $error));

        $api = new Api(MOCK_APPLICATION_KEY, MOCK_APPLICATION_SECRET, 'ovh-eu', MOCK_CONSUMER_KEY, $client);
        $api->get('/me', null, null, false);
    }

    public function testGetConsumerKey()
    {
        $api = new Api(MOCK_APPLICATION_KEY, MOCK_APPLICATION_SECRET, 'ovh-eu', MOCK_CONSUMER_KEY);
        $this->assertSame(MOCK_CONSUMER_KEY, $api->getConsumerKey());
    }


    /**
     * Test GET query args
     */
    public function testGetQueryArgs()
    {
        $client = new MockClient(new Response(200, [], '{}'));

        $api = new Api(MOCK_APPLICATION_KEY, MOCK_APPLICATION_SECRET, 'ovh-eu', MOCK_CONSUMER_KEY, $client);
        $api->get('/me/api/credential?applicationId=49', ['status' => 'pendingValidation'], null, false);

        $calls = $client->calls;
        $this->assertCount(1, $calls);

        $req = $calls[0]['request'];
        $this->assertSame('GET', $req->getMethod());
        $this->assertSame('https://eu.api.ovh.com/1.0/me/api/credential?applicationId=49&status=pendingValidation', $req->getUri()->__toString());
    }

    /**
     * Test GET overlapping query args
     */
    public function testGetOverlappingQueryArgs()
    {
        $client = new MockClient(new Response(200, [], '{}'));

        $api = new Api(MOCK_APPLICATION_KEY, MOCK_APPLICATION_SECRET, 'ovh-eu', MOCK_CONSUMER_KEY, $client);
        $api->get('/me/api/credential?applicationId=49&status=pendingValidation', ['status' => 'expired', 'test' => "success"], null, false);

        $calls = $client->calls;
        $this->assertCount(1, $calls);

        $req = $calls[0]['request'];
        $this->assertSame('GET', $req->getMethod());
        $this->assertSame('https://eu.api.ovh.com/1.0/me/api/credential?applicationId=49&status=expired&test=success', $req->getUri()->__toString());
    }

    /**
     * Test GET boolean query args
     */
    public function testGetBooleanQueryArgs()
    {
        $client = new MockClient(new Response(200, [], '{}'));

        $api = new Api(MOCK_APPLICATION_KEY, MOCK_APPLICATION_SECRET, 'ovh-eu', MOCK_CONSUMER_KEY, $client);
        $api->get('/me/api/credential', ['dryRun' => true, 'notDryRun' => false], null, false);

        $calls = $client->calls;
        $this->assertCount(1, $calls);

        $req = $calls[0]['request'];
        $this->assertSame('GET', $req->getMethod());
        $this->assertSame('https://eu.api.ovh.com/1.0/me/api/credential?dryRun=true&notDryRun=false', $req->getUri()->__toString());
    }

    /**
     * Test valid provided endpoint
     */
    public function testProvidedUrl()
    {
        foreach ([
            ['endpoint' => 'http://api.ovh.com/1.0',  'expectedUrl' => 'http://api.ovh.com/1.0'],
            ['endpoint' => 'https://api.ovh.com/1.0', 'expectedUrl' => 'https://api.ovh.com/1.0'],
            ['endpoint' => 'ovh-eu',                  'expectedUrl' => 'https://eu.api.ovh.com/1.0'],
        ] as $test) {
            $client = new MockClient(new Response(200, [], '{}'));

            $api = new Api(MOCK_APPLICATION_KEY, MOCK_APPLICATION_SECRET, $test['endpoint'], MOCK_CONSUMER_KEY, $client);
            $api->get('/me/api/credential', null, null, false);

            $calls = $client->calls;
            $this->assertCount(1, $calls);

            $req = $calls[0]['request'];
            $this->assertSame('GET', $req->getMethod());
            $this->assertSame($test['expectedUrl'] . '/me/api/credential', $req->getUri()->__toString());
        }
    }

    /**
     * Test missing header X-OVH-Application on requestCredentials
     */
    public function testMissingOvhApplicationHeaderOnRequestCredentials()
    {
        $client = new MockClient(
            new Response(200, [], MOCK_TIME),
            new Response(200, [], '{"validationUrl":"https://api.ovh.com/login/?credentialToken=token","consumerKey":"consumer_remote","state":"pendingValidation"}'),
        );

        $api = new Api(MOCK_APPLICATION_KEY, MOCK_APPLICATION_SECRET, 'ovh-eu', MOCK_CONSUMER_KEY, $client);
        $api->requestCredentials([]);

        $calls = $client->calls;
        $this->assertCount(2, $calls);

        $req = $calls[0]['request'];
        $this->assertSame('GET', $req->getMethod());
        $this->assertSame('https://eu.api.ovh.com/1.0/auth/time', $req->getUri()->__toString());

        $req = $calls[1]['request'];
        $this->assertSame('POST', $req->getMethod());
        $this->assertSame('https://eu.api.ovh.com/1.0/auth/credential', $req->getUri()->__toString());
        $this->assertSame(MOCK_APPLICATION_KEY, $req->getHeaderLine('X-Ovh-Application'));
        $this->assertSame(MOCK_CONSUMER_KEY, $req->getHeaderLine('X-Ovh-Consumer'));
        $this->assertSame(MOCK_TIME, $req->getHeaderLine('X-Ovh-Timestamp'));
    }

    public function testCallSignature()
    {
        // GET /auth/time
        $mocks = [new Response(200, [], MOCK_TIME)];
        // (GET,POST,PUT,DELETE) x  (/auth,/unauth)
        for ($i = 0; $i < 8; $i++) {
            $mocks[] = new Response(200, [], '{}');
        }
        $client = new MockClient(...$mocks);

        $api = new Api(MOCK_APPLICATION_KEY, MOCK_APPLICATION_SECRET, 'ovh-eu', MOCK_CONSUMER_KEY, $client);
        $body = ["a" => "b", "c" => "d"];

        // Authenticated calls
        $api->get('/auth');
        $api->post('/auth', $body);
        $api->put('/auth', $body);
        $api->delete('/auth');

        // Unauth calls
        $api->get('/unauth', null, null, false);
        $api->post('/unauth', $body, null, false);
        $api->put('/unauth', $body, null, false);
        $api->delete('/unauth', null, null, false);

        $calls = $client->calls;
        $this->assertCount(9, $calls);

        $req = $calls[0]['request'];
        $this->assertSame('GET', $req->getMethod());
        $this->assertSame('https://eu.api.ovh.com/1.0/auth/time', $req->getUri()->__toString());

        foreach ([
            1 => ['method' => 'GET',    'sig' => '$1$e9556054b6309771395efa467c22e627407461ad'],
            2 => ['method' => 'POST',   'sig' => '$1$ec2fb5c7a81f64723c77d2e5b609ae6f58a84fc1'],
            3 => ['method' => 'PUT',    'sig' => '$1$8a75a9e7c8e7296c9dbeda6a2a735eb6bd58ec4b'],
            4 => ['method' => 'DELETE', 'sig' => '$1$a1eecd00b3b02b6cf5708b84b9ff42059a950d85'],
        ] as $i => $test) {
            $req = $calls[$i]['request'];
            $this->assertSame($test['method'], $req->getMethod());
            $this->assertSame('https://eu.api.ovh.com/1.0/auth', $req->getUri()->__toString());
            $this->assertSame(MOCK_APPLICATION_KEY, $req->getHeaderLine('X-Ovh-Application'));
            $this->assertSame(MOCK_CONSUMER_KEY, $req->getHeaderLine('X-Ovh-Consumer'));
            $this->assertSame(MOCK_TIME, $req->getHeaderLine('X-Ovh-Timestamp'));
            $this->assertSame($test['sig'], $req->getHeaderLine('X-Ovh-Signature'));
            if ($test['method'] == 'POST' || $test['method'] == 'PUT') {
                $this->assertSame('application/json; charset=utf-8', $req->getHeaderLine('Content-Type'));
            }
        }

        foreach (['GET', 'POST', 'PUT', 'DELETE'] as $i => $method) {
            $req = $calls[$i + 5]['request'];
            $this->assertSame($method, $req->getMethod());
            $this->assertSame('https://eu.api.ovh.com/1.0/unauth', $req->getUri()->__toString());
            $this->assertSame(MOCK_APPLICATION_KEY, $req->getHeaderLine('X-Ovh-Application'));
            $this->assertNotTrue($req->hasHeader('X-Ovh-Consumer'));
            $this->assertNotTrue($req->hasHeader('X-Ovh-Timestamp'));
            $this->assertNotTrue($req->hasHeader('X-Ovh-Signature'));
            if ($method == 'POST' || $method == 'PUT') {
                $this->assertSame('application/json; charset=utf-8', $req->getHeaderLine('Content-Type'));
            }
        }
    }

    public function testVersionInUrl()
    {
        // GET /auth/time
        $mocks = [new Response(200, [], MOCK_TIME)];
        // GET) x  (/1.0/call,/v1/call,/v2/call)
        for ($i = 0; $i < 3; $i++) {
            $mocks[] = new Response(200, [], '{}');
        }
        $client = new MockClient(...$mocks);

        $api = new Api(MOCK_APPLICATION_KEY, MOCK_APPLICATION_SECRET, 'ovh-eu', MOCK_CONSUMER_KEY, $client);

        $api->get('/call');
        $api->get('/v1/call');
        $api->get('/v2/call');

        $calls = $client->calls;
        $this->assertCount(4, $calls);

        $req = $calls[0]['request'];
        $this->assertSame('GET', $req->getMethod());
        $this->assertSame('https://eu.api.ovh.com/1.0/auth/time', $req->getUri()->__toString());

        foreach ([
            1 => ['path' => '1.0/call', 'sig' => '$1$7f2db49253edfc41891023fcd1a54cf61db05fbb'],
            2 => ['path' => 'v1/call',  'sig' => '$1$e6e7906d385eb28adcbfbe6b66c1528a42d741ad'],
            3 => ['path' => 'v2/call',  'sig' => '$1$bb63b132a6f84ad5433d0c534d48d3f7c3804285'],
        ] as $i => $test) {
            $req = $calls[$i]['request'];
            $this->assertSame('GET', $req->getMethod());
            $this->assertSame('https://eu.api.ovh.com/' . $test['path'], $req->getUri()->__toString());
            $this->assertSame(MOCK_APPLICATION_KEY, $req->getHeaderLine('X-Ovh-Application'));
            $this->assertSame(MOCK_CONSUMER_KEY, $req->getHeaderLine('X-Ovh-Consumer'));
            $this->assertSame(MOCK_TIME, $req->getHeaderLine('X-Ovh-Timestamp'));
            $this->assertSame($test['sig'], $req->getHeaderLine('X-Ovh-Signature'));
        }
    }

    public function testEmptyResponseBody()
    {
        $client = new MockClient(
            // GET /auth/time
            new Response(200, [], MOCK_TIME),
            // POST /domain/zone/nonexisting.ovh/refresh
            new Response(204, [], ''),
        );

        $api = new Api(MOCK_APPLICATION_KEY, MOCK_APPLICATION_SECRET, 'ovh-eu', MOCK_CONSUMER_KEY, $client);
        $response = $api->post('/domain/zone/nonexisting.ovh/refresh');
        $this->assertSame(null, $response);

        $calls = $client->calls;
        $this->assertCount(2, $calls);

        $req = $calls[0]['request'];
        $this->assertSame('GET', $req->getMethod());
        $this->assertSame('https://eu.api.ovh.com/1.0/auth/time', $req->getUri()->__toString());

        $req = $calls[1]['request'];
        $this->assertSame('POST', $req->getMethod());
        $this->assertSame('https://eu.api.ovh.com/1.0/domain/zone/nonexisting.ovh/refresh', $req->getUri()->__toString());
    }
}
