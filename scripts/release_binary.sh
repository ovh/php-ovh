#!/bin/bash
#
# This script is used to build and deploy the binary from the current version on github
# Usage ./scripts/release_binary.sh <version> <githubUser> <githubToken>
#

set -e

VERSION="$1"
USER="$2"
TOKEN="$3"

if [ -z "$VERSION"  ];
then
  echo "Missing version" >&2
  exit 1
fi

if  [ -z "$USER" ];
then
  echo "Missing github user" >&2
  exit 1
fi

if  [ -z "$TOKEN" ];
then
  echo "Missing github token" >&2
  exit 1
fi

cd /tmp
mkdir -p php-ovh-bin
cd php-ovh-bin

curl -sS https://getcomposer.org/installer | php

echo '{
    "name": "Example Application",
    "description": "This is an example of OVH APIs wrapper usage",
    "require": {
        "ovh/ovh": "2.x"
    }
}' > composer.json

php composer.phar install

echo '<?php
require __DIR__ . "/vendor/autoload.php";
use \Ovh\Api;

// Informations about your application
$applicationKey 	= "your_app_key";
$applicationSecret 	= "your_app_secret";
$consumer_key 		= "your_consumer_key";
$endpoint 		= 'ovh-eu';

// Get servers list
$conn = new Api(    $applicationKey,
                    $applicationSecret,
                    $endpoint,
                    $consumer_key);

try {
	// Insert your code here
	$me = $conn->get("/me");
	print_r( $me );

} catch ( Exception $ex ) {
	print_r( $ex->getMessage() );
}' > script.php

zip -r php-ovh-$VERSION-with-dependencies.zip .

ID=$(curl https://api.github.com/repos/ovh/php-ovh/releases/tags/v$VERSION -u $USER:$TOKEN | jq -r '.id')

curl -X POST -d @php-ovh-$VERSION-with-dependencies.zip -H "Content-Type: application/zip"  https://uploads.github.com/repos/ovh/php-ovh/releases/$ID/assets?name=php-ovh-$VERSION-with-dependencies.zip -i -u $USER:$TOKEN
rm php-ovh-$VERSION-with-dependencies.zip

tar -czf php-ovh-$VERSION-with-dependencies.tar.gz .
curl -X POST -d @php-ovh-$VERSION-with-dependencies.tar.gz -H "Content-Type: application/zip"  https://uploads.github.com/repos/ovh/php-ovh/releases/$ID/assets?name=php-ovh-$VERSION-with-dependencies.tar.gz -i -u $USER:$TOKEN
