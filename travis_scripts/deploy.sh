#!/usr/bin/env bash

# The -e flag causes the script to exit as soon as one command returns a non-zero exit code.
# The -v flag makes the shell print all lines in the script before executing them
set -ev

echo "deploy.sh TRAVIS_BRANCH=$TRAVIS_BRANCH TRAVIS_PULL_REQUEST=$TRAVIS_PULL_REQUEST TRAVIS_TAG=$TRAVIS_TAG DEPLOY=$DEPLOY"

if [[ $DEPLOY == 1 ]]; then
    # Is this not a Pull Request?
    if [[ "$TRAVIS_PULL_REQUEST" != "false" ]]; then
        echo "deploy disabled on pull request"
        exit 1
    fi
    # Is there a tag ?
    if [[ -z "$TRAVIS_TAG" ]]; then
        echo "deploy disabled if no tag provided"
        exit 1
    fi

    # deployment
    echo "create deploy temp directory"
    rm -Rf /tmp/better-world-quotes-widget
    mkdir -p /tmp/better-world-quotes-widget

    echo "copy all project files"
    cp -R src/* /tmp/better-world-quotes-widget
    cp -R *.md /tmp/better-world-quotes-widget

    # TODO minify the css and js files

    echo "add index.php Silence is golden in each directory where there isn't an index.php"
    echo "<?php // Silence is golden" > /tmp/index.php
    find /tmp/better-world-quotes-widget -type d \! -exec test -e '{}/index.php' \; -exec cp /tmp/index.php {} \;

    echo "zip the files"
    cd /tmp
    zip -r better-world-quotes-widget.zip better-world-quotes-widget

    echo "create checksum file"
    sha1sum better-world-quotes-widget.zip > better-world-quotes-widget.zip.sha1

    cd -
fi
