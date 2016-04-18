#!/bin/bash

VERSION="$1"
USER="$2"
TOKEN="$3"

cd /tmp
mkdir php-ovh-bin
cd php-ovh-bin

php -r "readfile('https://getcomposer.org/installer');" > composer-setup.php
php -r "if (hash('SHA384', file_get_contents('composer-setup.php')) === '7228c001f88bee97506740ef0888240bd8a760b046ee16db8f4095c0d8d525f2367663f22a46b48d072c816e7fe19959') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
php composer-setup.php
php -r "unlink('composer-setup.php');"

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
