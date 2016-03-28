#!/bin/bash
#
# Usage: ./scripts/bump-version.sh <new.version.number>
#

PCRE_MATCH_VERSION="[0-9]+\.[0-9]+\.[0-9]+"
PCRE_MATCH_VERSION_BOUNDS="(^|[- v/'\"])${PCRE_MATCH_VERSION}([- /'\"]|$)"
VERSION="$1"

if ! echo "$VERSION" | grep -Pq "${PCRE_MATCH_VERSION}"; then
    echo "Usage: ./scripts/bump-version.sh <new.version.number>"
    echo "    <new.version.number> must be a valid 3 digit version number"
    echo "    Make sure to double check 'git diff' before commiting anything you'll regret on master"
    exit 1
fi

# Edit text files matching the PCRE, do *not* patch .git folder
grep -PIrl "${PCRE_MATCH_VERSION_BOUNDS}" $(ls) | xargs sed -ir "s/${PCRE_MATCH_VERSION_BOUNDS}/"'\1'"${VERSION}"'\2/g'

