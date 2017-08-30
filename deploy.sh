#!/usr/bin/env bash
rm -Rf /tmp/better-world-quotes-widget
mkdir -p /tmp/better-world-quotes-widget

# copy all project files
cp -R admin languages public *.php *.md /tmp/better-world-quotes-widget

# TODO minify the css and js files

# add index.php Silence is golden in each directory where there isn't an index.php
echo "<?php // Silence is golden" > /tmp/index.php
find /tmp/better-world-quotes-widget -type d \! -exec test -e '{}/index.php' \; -exec cp /tmp/index.php {} \;

# zip the files
zip -r /tmp/better-world-quotes-widget.zip /tmp/better-world-quotes-widget

# create checksum file
sha1sum /tmp/better-world-quotes-widget.zip > /tmp/better-world-quotes-widget.zip.sha1