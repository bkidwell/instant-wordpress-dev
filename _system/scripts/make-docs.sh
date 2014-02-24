#!/bin/bash

# This script makes the HTML documentation in $root and $root/docs
#
# To run this script you need:
#   * asciidoc
#   * libxml2-utils
#   * source-highlight

cd_=`pwd`
cd `readlink -f \`dirname $0\`/../../docs`

if [ ! -e images/icons ]; then
    mkdir -p images/icons
    cp -r /usr/share/asciidoc/icons/* images/icons/
fi
asciidoc --attribute=icons --backend=html5 instant-wordpress-dev.asciidoc 

cd ..

asciidoc --attribute=icons --backend=html5 README.asciidoc
sed -i \
  's|https://github.com/bkidwell/instant-wordpress-dev/blob/master/docs/instant-wordpress-dev.asciidoc|docs/instant-wordpress-dev.html|g' \
  README.html
sed -i \
  's|https://raw2.github.com/bkidwell/instant-wordpress-dev/master/docs/images/instances.png|docs/images/instances.png|g' \
  README.html

asciidoc --attribute=icons --backend=html5 LICENSE.asciidoc

cd "$cd_"
