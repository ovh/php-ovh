# OVHcloud APIs lightweight PHP wrapper

[![PHP Wrapper for OVH APIs](https://github.com/ovh/php-ovh/blob/master/img/logo.png)](https://packagist.org/packages/ovh/ovh)

[![Source Code](https://img.shields.io/badge/source-ovh/php--ovh-blue.svg?style=flat-square)](https://github.com/ovh/php-ovh)
[![Build Status](https://img.shields.io/github/actions/workflow/status/ovh/php-ovh/ci.yaml?label=CI&logo=github&style=flat-square)](https://github.com/ovh/php-ovh/actions?query=workflow%3ACI)
[![Codecov Code Coverage](https://img.shields.io/codecov/c/gh/ovh/php-ovh?label=codecov&logo=codecov&style=flat-square)](https://codecov.io/gh/ovh/php-ovh)
[![Total Downloads](https://img.shields.io/packagist/dt/ovh/ovh.svg?style=flat-square)](https://packagist.org/packages/ovh/ovh)

This PHP package is a lightweight wrapper for OVHcloud APIs.

The easiest way to use OVHcloud APIs in your PHP applications.

Compatible with PHP 7.4, 8.0, 8.1, 8.2.

## Installation

Install this wrapper and integrate it inside your PHP application with [Composer](https://getcomposer.org):

    composer require ovh/ovh

## Basic usage

```php
<?php
require __DIR__ . '/vendor/autoload.php';
use \Ovh\Api;

// Api credentials can be retrieved from the urls specified in the "Supported endpoints" section below.
$ovh = new Api($applicationKey,
                $applicationSecret,
                $endpoint,
                $consumerKey);
echo 'Welcome '.$ovh->get('/me')['firstname'];
```

## Advanced usage

### Handle exceptions

Under the hood, ```php-ovh``` uses [Guzzle](http://docs.guzzlephp.org/en/latest/quickstart.html) by default to issue API requests.

If everything goes well, it will return the response directly as shown in the examples above.

If there is an error like a missing endpoint or object (404), an authentication or authorization error (401 or 403) or a parameter error, the Guzzle will raise a ``GuzzleHttp\Exception\ClientException`` exception. For server-side errors (5xx), it will raise a ``GuzzleHttp\Exception\ServerException`` exception.

You can get the error details with a code like:

```php
try {
    echo "Welcome " . $ovh->get('/me')['firstname'];
} catch (GuzzleHttp\Exception\ClientException $e) {
    $response = $e->getResponse();
    $responseBodyAsString = $response->getBody()->getContents();
    echo $responseBodyAsString;
}
```

### Customize HTTP client configuration

You can inject your own HTTP client with your specific configuration. For instance, you can edit user-agent and timeout for all your requests

```php
<?php
require __DIR__ . '/vendor/autoload.php';
use \Ovh\Api;
use GuzzleHttp\Client;

// Instantiate a custom Guzzle HTTP client and tweak it
$client = new Client();
$client->setDefaultOption('timeout', 1);
$client->setDefaultOption('headers', ['User-Agent' => 'api_client']);

// Api credentials can be retrieved from the urls specified in the "Supported endpoints" section below.
// Inject the custom HTTP client as the 5th argument of the constructor
$ovh = new Api($applicationKey,
                $applicationSecret,
                $endpoint,
                $consumerKey,
                $client);

echo 'Welcome '.$ovh->get('/me')['firstname'];
```

### Authorization flow

This flow will allow you to request consumerKey from an OVHcloud account owner.
After allowing access to his account, he will be redirected to your application.

See "OVHcloud API authentication" section below for more information about the authorization flow.


```php
use \Ovh\Api;
session_start();

// Api credentials can be retrieved from the urls specified in the "Supported endpoints" section below.
$ovh = new Api($applicationKey,
                $applicationSecret,
                $endpoint);

// Specify the list of API routes you want to request
$rights = [
    [ 'method' => 'GET',  'path' => '/me*' ],
];

// After allowing your application access, the customer will be redirected to this URL.
$redirectUrl = 'https://your_application_redirect_url'

$credentials = $conn->requestCredentials($rights, $redirectUrl);

// Save consumer key and redirect to authentication page
$_SESSION['consumerKey'] = $credentials['consumerKey'];
header('location: '. $credentials['validationUrl']);
// After successful redirect, the consumerKey in the session will be activated and you will be able to use it to make API requests like in the "Basic usage" section above.
```

### Code sample: Enable network burst on GRA1 dedicated servers

Here is a more complex example of how to use the wrapper to enable network burst on GRA1 dedicated servers.

```php
<?php
require __DIR__ . '/vendor/autoload.php';
use \Ovh\Api;

// Api credentials can be retrieved from the urls specified in the "Supported endpoints" section below.
$ovh = new Api($applicationKey,
                $applicationSecret,
                $endpoint,
                $consumerKey);

// Load the list of dedicated servers
$servers = $conn->get('/dedicated/server/');
foreach ($servers as $server) {
    // Load the server details
    $details = $conn->get('/dedicated/server/'.$server);
    // Filter servers only inside GRA1
    if ($details['datacenter'] == 'gra1') {
        // Activate burst on server
        $content = ['status' => 'active'];
        $conn->put('/dedicated/server/'.$server.'/burst', $content);
        echo 'Burst enabled on '.$server;
    }
}
```

### More code samples

Do you want to use OVH APIs? Maybe the script you want is already written in the [example part](examples/README.md) of this repository!

## OVHcloud API authentication

To use the OVHcloud APIs you need three credentials:

* An application key
* An application secret
* A consumer key

The application key and secret are not granting access to a specific account and are unique to identify your application.
The consumer key is used to grant access to a specific OVHcloud account to a specified application.

They can be created separately if your application is intended to be used by multiple accounts (your app will need to implement an authorization flow).
In the authorization flow, the customer will be prompted to allow access to his account to your application, then he will be redirected to your application.

They can also be created together if your application is intended to use only your own OVHcloud account.

## Supported endpoints

### OVHcloud Europe

* ```$endpoint = 'ovh-eu';```
* Documentation: <https://eu.api.ovh.com/>
* Console: <https://eu.api.ovh.com/console>
* Create application credentials (generate only application credentials, your app will need to implement an authorization flow): <https://eu.api.ovh.com/createApp/>
* Create account credentials (all keys at once for your own account only): <https://eu.api.ovh.com/createToken/>
* Community support: api-subscribe@ml.ovh.net

### OVHcloud US

* ```$endpoint = 'ovh-us';```
* Documentation: <https://api.us.ovhcloud.com/>
* Console: <https://api.us.ovhcloud.com/console>
* Create application credentials (generate only application credentials, your app will need to implement an authorization flow): <https://api.us.ovhcloud.com/createApp/>
* Create account credentials (all keys at once for your own account only): <https://api.us.ovhcloud.com/createToken/>

### OVHcloud North America / Canada

* ```$endpoint = 'ovh-ca';```
* Documentation: <https://ca.api.ovh.com/>
* Console: <https://ca.api.ovh.com/console>
* Create application credentials (generate only application credentials, your app will need to implement an authorization flow): <https://ca.api.ovh.com/createApp/>
* Create account credentials (all keys at once for your own account only): <https://ca.api.ovh.com/createToken/>
* Community support: api-subscribe@ml.ovh.net

### So you Start Europe

* ```$endpoint = 'soyoustart-eu';```
* Documentation: <https://eu.api.soyoustart.com/>
* Console: <https://eu.api.soyoustart.com/console/>
* Create application credentials (generate only application credentials, your app will need to implement an authorization flow): <https://eu.api.soyoustart.com/createApp/>
* Create account credentials (all keys at once for your own account only): <https://eu.api.soyoustart.com/createToken/>
* Community support: api-subscribe@ml.ovh.net

### So you Start North America

* ```$endpoint = 'soyoustart-ca';```
* Documentation: <https://ca.api.soyoustart.com/>
* Console: <https://ca.api.soyoustart.com/console/>
* Create application credentials (generate only application credentials, your app will need to implement an authorization flow): <https://ca.api.soyoustart.com/createApp/>
* Create account credentials (all keys at once for your own account only): <https://ca.api.soyoustart.com/createToken/>
* Community support: api-subscribe@ml.ovh.net

### Kimsufi Europe

* ```$endpoint = 'kimsufi-eu';```
* Documentation: <https://eu.api.kimsufi.com/>
* Console: <https://eu.api.kimsufi.com/console/>
* Create application credentials (generate only application credentials, your app will need to implement an authorization flow): <https://eu.api.kimsufi.com/createApp/>
* Create account credentials (all keys at once for your own account only): <https://eu.api.kimsufi.com/createToken/>
* Community support: api-subscribe@ml.ovh.net

### Kimsufi North America

* ```$endpoint = 'kimsufi-ca';```
* Documentation: <https://ca.api.kimsufi.com/>
* Console: <https://ca.api.kimsufi.com/console/>
* Create application credentials (generate only application credentials, your app will need to implement an authorization flow): <https://ca.api.kimsufi.com/createApp/>
* Create account credentials (all keys at once for your own account only): <https://ca.api.kimsufi.com/createToken/>
* Community support: api-subscribe@ml.ovh.net

## Building documentation

Documentation is based on phpdocumentor and inclued in the project.
To generate documentation, it's possible to use directly:

    composer phpdoc

Documentation is available in docs/ directory.

## Code check / Linting

Code check is based on PHP CodeSniffer and inclued in the project.
To check code, it's possible to use directly:

    composer phpcs

Code linting is based on PHP Code Beautifier and Fixer and inclued in the project.
To lint code, it's possible to use directly:

    composer phpcbf

## Testing

Tests are based on phpunit and inclued in the project.
To run functionals tests, you need to provide valid API credentials, that you can provide them via environment:

    APP_KEY=xxx APP_SECRET=xxx CONSUMER=xxx ENDPOINT=xxx composer phpunit

## Contributing

Please see [CONTRIBUTING](https://github.com/ovh/php-ovh/blob/master/CONTRIBUTING.rst) for details.

## Credits

[All Contributors from this repo](https://github.com/ovh/php-ovh/contributors)

## License

 (Modified) BSD license. Please see [LICENSE](https://github.com/ovh/php-ovh/blob/master/LICENSE) for more information.
