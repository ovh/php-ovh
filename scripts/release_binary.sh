#!/bin/bash
#
# This script is used to build and deploy the binary from the current version on github
# Usage ./scripts/release_binary.sh <version> <githubUser> <githubToken>
#

set -e

VERSION="$1"
USER="$2"
TOKEN="$3"

function usage() {
    echo "Usage: $0 <version> <githubUser> <githubToken>"
    echo "Hint: "
    echo " - Make sure there is outstanding changes in the current directory"
    echo " - Make sure the requested version does not exist yet"
    echo " - You may visit https://help.github.com/articles/creating-an-access-token-for-command-line-use/ to generate your Github Token"
}

#
# Validate input
#

if [ -z "$VERSION"  ];
then
  echo "Missing version" >&2
  usage
  exit 1
fi

if  [ -z "$USER" ];
then
  echo "Missing github user" >&2
  usage
  exit 1
fi

if  [ -z "$TOKEN" ];
then
  echo "Missing github token" >&2
  usage
  exit 1
fi

#
# Validate repository
#

if [ -n "$(git status --porcelain)" ]
then
    echo "Working repository is not clean. Please commit or stage any pending changes." >&2
    usage
    exit 1
fi

CURRENTTAG=$(git describe --tag --exact-match 2>/dev/null || echo "")
if [ "${CURRENTTAG}" != "${VERSION}" ]
then
    if [ -n "${CURRENTTAG}" ]
    then
        echo "The current commit is already tagged with ${CURRENTTAG} which is not the requested release ${VERSION}" >&2
        usage
        exit 1
    fi

    if git rev-parse refs/tags/${VERSION} &>/dev/null
    then
        echo "The requested version ${VERSION} already exists" >&2
        usage
        exit 1
    fi
fi

#
# Release
#

PROJECT_NAME=$(git remote -v | grep 'github.*push' | awk '{split($2, a, "/"); split(a[2], b, "."); print b[1]}')
echo "Releasing ${PROJECT_NAME} version ${VERSION}..."

git tag -m "Releasing ${PROJECT_NAME} version ${VERSION}" "${VERSION}"
git push --tags
git push

cd /tmp
mkdir -p ${PROJECT_NAME}-bin
cd ${PROJECT_NAME}-bin

curl -sS https://getcomposer.org/installer | php

# FIXME: this will require the release to already be uploaded on packagist.org
cat > composer.json << EOF
{
    "name": "Example Application",
    "description": "This is an example of OVH APIs wrapper usage",
    "require": {
        "ovh/ovh": "${VERSION}"
    }
}
EOF

php composer.phar install

cat > script.php << EOF
<?php
require __DIR__ . "/vendor/autoload.php";
use \Ovh\Api;

// Informations about your application
// You may create new credentials on https://api.ovh.com/createToken/index.cgi
$applicationKey     = "your_app_key";
$applicationSecret  = "your_app_secret";
$consumer_key       = "your_consumer_key";
$endpoint           = "ovh-eu";

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
}
EOF


ID=$(curl https://api.github.com/repos/ovh/${PROJECT_NAME}/releases/tags/v${VERSION} -u "${USER}:${TOKEN}" | jq -r '.id')

zip -r ${PROJECT_NAME}-${VERSION}-with-dependencies.zip .
curl -X POST -d @${PROJECT_NAME}-${VERSION}-with-dependencies.zip -H "Content-Type: application/zip"  "https://uploads.github.com/repos/ovh/${PROJECT_NAME}/releases/${ID}/assets?name=${PROJECT_NAME}-${VERSION}-with-dependencies.zip" -i -u "${USER}:${TOKEN}"
rm ${PROJECT_NAME}-${VERSION}-with-dependencies.zip

tar -czf ${PROJECT_NAME}-${VERSION}-with-dependencies.tar.gz .
curl -X POST -d @${PROJECT_NAME}-${VERSION}-with-dependencies.tar.gz -H "Content-Type: application/gzip"  "https://uploads.github.com/repos/ovh/${PROJECT_NAME}/releases/${ID}/assets?name=${PROJECT_NAME}-${VERSION}-with-dependencies.tar.gz" -i -u "${USER}:${TOKEN}"
rm -f ${PROJECT_NAME}-${VERSION}-with-dependencies.tar.gz

