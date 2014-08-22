<?php
# Copyright (c) 2013-2014, OVH SAS.
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

use Ovh\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Subscriber\Mock;

/**
 * Test Api class
 *
 * @package Ovh
 * @category Ovh
 * @author Vincent Cassé <vincent.casse@ovh.net>
 */
class ApiTest extends \PHPUnit_Framework_TestCase {

	/**
	 * Define id to create object
	 */
	protected function setUp()
    {
		$this->application_key = 'app_key';
		$this->application_secret = 'app_secret';
		$this->consumer_key = 'consumer';
		$this->endpoint = 'ovh-eu';

        $this->client = new Client();
    }

    /**
     * Get private and protected method to unit test it
     */
    protected static function getPrivateMethod($name)
    {
        $class = new \ReflectionClass('Ovh\Api');
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }

    protected static function getPrivateProperty($name)
    {
        $class = new \ReflectionClass('Ovh\Api');
        $property = $class->getProperty($name);
        $property->setAccessible(true);
        return $property;
    }

	/**
	 * Test the compute of time delta
	 */
	public function testTimeDeltaCompute()
	{
		$delay = 10;
        $mock = new Mock([
        	"HTTP/1.1 200 OK\r\n\r\n". (time()-$delay),
        ]);
		$this->client->getEmitter()->attach($mock);

		$invoker = self::getPrivateMethod('calculateTimeDelta');
		$property = self::getPrivateProperty('time_delta');
		$api = new Api($this->application_key, $this->application_secret, $this->endpoint, $this->consumer_key, $this->client);
		$invoker->invokeArgs($api, array()) ;

		$time_delta = $property->getValue($api);
		$this->assertNotNull($time_delta);
		$this->assertEquals($time_delta, $delay * -1 );
	}

	/**
	 * Test if consumer key is replaced
	 */
	public function testIfConsumerKeyIsReplace()
	{
		$delay = 10;
        $mock = new Mock([
        	"HTTP/1.1 200 OK\r\n\r\n". '{"validationUrl":"https://api.ovh.com/login/?credentialToken=token","consumerKey":"consumer_remote","state":"pendingValidation"}'
        ]);
		$this->client->getEmitter()->attach($mock);

		$property = self::getPrivateProperty('consumer_key');
		$api = new Api($this->application_key, $this->application_secret, $this->endpoint, $this->consumer_key, $this->client);
		$accessRules = array( json_decode(' { "method": "GET", "path": "/*" } ') );

		$credentials = $api->requestCredentials($accessRules);
		$consumer_key = $property->getValue($api);

		$this->assertEquals( $consumer_key , $credentials["consumerKey"]);
		$this->assertNotEquals( $consumer_key, $this->consumer_key );
	}

	/**
	 * Test invalid applicationKey
	 */
	public function testInvalidApplicationKey()
	{
		$this->setExpectedException(
          '\GuzzleHttp\Exception\ClientException'
        );

		$delay = 10;
        $mock = new Mock([
        	"HTTP/1.1 401 Unauthorized\r\nContent-Type: application/json; charset=utf-8\r\nContent-Length: 37\r\n\r\n{\"message\":\"Invalid application key\"}"
        ]);
		$this->client->getEmitter()->attach($mock);

		$property = self::getPrivateProperty('consumer_key');
		$api = new Api($this->application_key, $this->application_secret, $this->endpoint, $this->consumer_key, $this->client);
		$accessRules = array( json_decode(' { "method": "GET", "path": "/*" } ') );

		$credentials = $api->requestCredentials($accessRules);
		$consumer_key = $property->getValue($api);

		$this->assertEquals( $consumer_key , $credentials["consumerKey"]);
		$this->assertNotEquals( $consumer_key, $this->consumer_key );
	}

	/**
	 * Test invalid rights
	 */
	public function testInvalidRight()
	{
		$this->setExpectedException(
          '\GuzzleHttp\Exception\ClientException'
        );

		$delay = 10;
        $mock = new Mock([
        	"HTTP/1.1 403 Forbidden\r\nContent-Type: application/json; charset=utf-8\r\nContent-Length: 37\r\n\r\n{\"message\":\"Invalid credentials\"}"
        ]);
		$this->client->getEmitter()->attach($mock);

		$api = new Api($this->application_key, $this->application_secret, $this->endpoint, $this->consumer_key, $this->client);

		$invoker = self::getPrivateMethod('rawCall');
		$invoker->invokeArgs($api, array('GET', '/me')) ;
	}
}

