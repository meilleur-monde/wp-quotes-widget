#!/usr/bin/env bash

# The -e flag causes the script to exit as soon as one command returns a non-zero exit code.
# The -v flag makes the shell print all lines in the script before executing them
set -ev

# Search for PHP syntax errors for all php version
# noinspection UnresolvedVariable
if [[ "$PHPLINT" == "1" ]]; then
    npm run phplint --silent
fi

#additional tests on certain plateform
if [[ "$SNIFF" == "1" ]]; then
    # Run the code through eslint
    npm run eslint --silent
    # Run the code through stylelint checker
    npm run stylelint --silent
    # WordPress Coding Standards
        # @link https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards
        # @link http://pear.php.net/package/PHP_CodeSniffer/
        # -p flag: Show progress of the run.
        # -s flag: Show sniff codes in all reports.
        # -v flag: Print verbose output.
        # -n flag: Do not print warnings (shortcut for --warning-severity=0)
        # --standard: Use WordPress as the standard.
        # --extensions: Only sniff PHP files.
    echo "########## PHPCS ###############"
    npm run phpcs --silent

    # PHP Copy/Paste Detector
    echo "########## PHP Copy/Paste Detector ###############"
    npm run phpcpd --verbose src --silent

    # PHP Mess Detector
    echo "########## PHP Mess Detector ###############"
    npm run phpmd src text ./.phpmd.xml --silent

    # PHPLOC: get some statistics
    echo "########## PHPLOC ###############"
    npm run phploc src --silent
fi

#sonar
if [[ $SONAR_QUBE == 1 ]]; then
    cat /home/travis/.sonarscanner/sonar-scanner-2.8/conf/sonar-scanner.properties
    find /home/travis/.sonarscanner/sonar-scanner-2.8
    sonar-scanner -X
fi