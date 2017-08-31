#!/usr/bin/env bash

# The -e flag causes the script to exit as soon as one command returns a non-zero exit code.
# The -v flag makes the shell print all lines in the script before executing them
set -ev

if [[ "$SNIFF" == "1" ]]; then
    # Set up WordPress installation.
    mkdir -p wordpress;
    # Use the Git mirror of WordPress.
    if [[ ! -d "./wordpress" ]]; then
        git clone --depth=1 --branch="$WP_VERSION" git://develop.git.wordpress.org/ wordpress;
    fi
    #install
    composer install
    # After CodeSniffer install you should refresh your path.
    phpenv rehash;
    # Install ESLINT: JavaScript Code Style checker
    # @link https://eslint.org/
    npm install --only=dev;
fi

