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

if [[ $SONAR_QUBE == 1 ]]; then
    #sonar plugins
    #curl -J -L https://sonarsource.bintray.com/Distribution/sonar-php-plugin/sonar-php-plugin-2.10.0.2087.jar -o $SONARQUBE_HOME/extensions/plugins/sonar-php-plugin-2.10.0.2087.jar
    #curl -O -J -L https://sonarsource.bintray.com/Distribution/sonar-javascript-plugin/sonar-javascript-plugin-3.1.1.5128.jar -o $SONARQUBE_HOME/extensions/plugins/sonar-javascript-plugin-3.1.1.5128.jar
    curl -O -J -L https://github.com/racodond/sonar-css-plugin/releases/download/4.9/sonar-css-plugin-4.9.jar -o $SONARQUBE_HOME/extensions/plugins/sonar-css-plugin-4.9.jar
fi


