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

use GuzzleHttp\Client;
use GuzzleHttp\Subscriber\Mock;
use Ovh\Api;

/**
 * Functional tests of Api class
 *
 * @package Ovh
 * @category Ovh
 * @author Vincent Cassé <vincent.casse@ovh.net>
 */
class ApiFunctionalTest extends \PHPUnit_Framework_TestCase {

	/**
	 * Define id to create object
	 */
	protected function setUp()
    {
		$this->application_key = getenv('APP_KEY');
		$this->application_secret = getenv('APP_SECRET');
		$this->consumer_key = getenv('CONSUMER');
		$this->endpoint = getenv('ENDPOINT');
		$this->rangeIP = '127.0.0.20/32';

        $this->client = new Client();
        $this->api = new Api($this->application_key, $this->application_secret, $this->endpoint, $this->consumer_key, $this->client);

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
	 * Test if result contains consumerKey and validationUrl
	 */
	public function testIfConsumerKeyIsReplace()
	{
		$property = self::getPrivateProperty('consumer_key');
		$accessRules = json_decode(' [
			{ "method": "GET", "path": "/*" },
			{ "method": "POST", "path": "/*" },
			{ "method": "PUT", "path": "/*" },
			{ "method": "DELETE", "path": "/*" }
		] ') ;

		$credentials = $this->api->requestCredentials($accessRules);
		$consumer_key = $property->getValue($this->api);

		$this->assertEquals( $consumer_key , $credentials["consumerKey"]);
		$this->assertNotEquals( $consumer_key, $this->consumer_key );
	}

	/**
	 * Test if put request on me
	 */
	public function testPutRestrictionAccessIp()
	{
		$api = new Api($this->application_key, $this->application_secret, $this->endpoint,  $this->consumer_key, $this->client);
		$invoker = self::getPrivateMethod('rawCall');
		$params = new \StdClass();
		$params->ip = $this->rangeIP;
		$params->rule = "deny";
		$params->warning = true;

		$result = $invoker->invokeArgs($api, array('POST', '/me/accessRestriction/ip', $params)) ;
		$this->assertNull( $result );
	}

	/**
	 * Test if get request on /me
	 */
	public function testGetRestrictionAccessIP()
	{
		$api = new Api($this->application_key, $this->application_secret, $this->endpoint,  $this->consumer_key, $this->client);
		$invoker = self::getPrivateMethod('rawCall');
		$result = $invoker->invokeArgs($api, array('GET', '/me/accessRestriction/ip')) ;
		foreach ($result as $restrictionId) {
			$restriction = $invoker->invokeArgs($api, array('GET', '/me/accessRestriction/ip/' . $restrictionId)) ;

			if ($restriction["ip"] == $this->rangeIP) {
				$this->assertTrue(true);
				break;
			}
		}
	}

	/**
	 * Test if delete request on /me
	 */
	public function testDeleteRestrictionAccessIP()
	{
		$api = new Api($this->application_key, $this->application_secret, $this->endpoint,  $this->consumer_key, $this->client);
		$invoker = self::getPrivateMethod('rawCall');

		$result = $invoker->invokeArgs($api, array('GET', '/me/accessRestriction/ip')) ;
		foreach ($result as $restrictionId) {
			$restriction = $invoker->invokeArgs($api, array('GET', '/me/accessRestriction/ip/' . $restrictionId)) ;

			if ($restriction["ip"] == $this->rangeIP) {
				$result = $invoker->invokeArgs($api, array('DELETE', '/me/accessRestriction/ip/'. $restrictionId)) ;
				$this->assertNull( $result );
				break;
			}
		}
	}

	/**
	 * Test if request without authentication works
	 */
	public function testIfRequestWithoutAuthenticationWorks()
	{
		$api = new Api($this->application_key, $this->application_secret, $this->endpoint,  NULL, $this->client);
		$invoker = self::getPrivateMethod('rawCall');
		$result = $invoker->invokeArgs($api, array('GET', '/xdsl/incidents')) ;
	}

}

