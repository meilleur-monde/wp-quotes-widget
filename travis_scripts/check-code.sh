#!/usr/bin/env bash

# The -e flag causes the script to exit as soon as one command returns a non-zero exit code.
# The -v flag makes the shell print all lines in the script before executing them
set -ev

# Search for PHP syntax errors for all php version
npm run phplint --silent

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
    npm run phpcs --silent
fi