#!/bin/bash

wpdev_root=`readlink -f \`dirname $0\``
php_cmd="php --define date.timezone=UTC"

# ----------------------------------------------------------------------
load_config() {
   BLACK='\E[30m'; RED='\E[31m'; GREEN='\E[32m'; YELLOW='\E[33m';
   BLUE='\E[34m'; MAGENTA='\E[35m'; CYAN='\E[36m'; WHITE='\E[37m'
   BOLD='\E[1m'; UNDERLINE='\E[4m'; REVERSE='\E[7m'; RESET='\E[0m'; 
   if [ ! -f "$wpdev_root/_data/config.sh" ]; then
      notice "\"$wpdev_root/_data/config.sh\" not found.\nCopying from \"config.sh.example\"."
      mkdir -p "$wpdev_root/_data"
      cp "$wpdev_root/_system/templates/config.sh.example" "$wpdev_root/_data/config.sh"
   fi
   . "$wpdev_root/_data/config.sh"
}

set_config_item() {
   $php_cmd "$wpdev_root/_system/scripts/set-config.php" $1 $2
   . "$wpdev_root/_data/config.sh"
}

# ----------------------------------------------------------------------
notice() {
   echo -e $YELLOW$*$RESET
}
msg() {
   echo -e $GREEN$*$RESET
}
error() {
   echo -e $BOLD$RED$*$RESET
}
bold() {
   echo -e $BOLD$*$RESET
}

# ----------------------------------------------------------------------
notice_install_php() {
   notice "Please install PHP 5.4+ ('php5-cli' in Ubuntu)."
   notice "Please install the following packages:"
   notice " - command-line interpreter for the php5 scripting language"
   notice " - MySQL module for php"
   notice " - GD module for php"
   notice "... in Ubuntu or Debian that would be:"
   notice " sudo apt-get install php5-cli php5-mysql php5-gd"
   echo ""
}
notice_install_pdo_mysql() {
   notice "Please install the MySQL module for php."
   notice "... in Ubuntu or Debian that would be:"
   notice " sudo apt-get install php5-mysql"
   echo ""
}
notice_install_gd() {
   notice "Please install the GD module for php."
   notice "... in Ubuntu or Debian that would be:"
   notice " sudo apt-get install php5-gd"
   echo ""
}
notice_install_mysql() {
   notice "Please install MySQL Server."
   notice "... in Ubuntu or Debian that would be:"
   notice " sudo apt-get install mysql-server"
   echo ""
}

check_requirements() {
   # Check for PHP
   msg "Checking PHP version..."
   which php >/dev/null
   if [ ! $? == 0 ]; then
      error "PHP was not found."
      notice_install_php
      php_good=no
   else
      php_good=yes
   fi

   # Check for PHP 5.4+
   if [ $php_good == yes ]; then
      msg "... `php --version 2>/dev/null | head -n 1 | cut -d " " -f 2`"
      $php_cmd --run "if (version_compare(PHP_VERSION, '5.4.0') < 0) { exit(1); }"
      if [ ! $? == 0 ]; then
         error "This application requires PHP 5.4+."
         notice_install_php
         php_good=no
      fi
   fi

   if [ $php_good == yes ]; then
      # Make sure a timezone is defined
      php --info 2>/dev/null | grep "^date.timezone" | grep "no value" >/dev/null
      if [ $? == 0 ]; then
         if [ "$TIMEZONE" == "FIXME" ]; then
            get_timezone
            if [ "$TIMEZONE" != "$timezone" ]; then
               set_config_item TIMEZONE $timezone
            fi
         fi
         php_cmd="php --define date.timezone=$TIMEZONE"
      else
         php_cmd=php
      fi

      # Check for php5-mysql
      $php_cmd --info|grep "^pdo_mysql\$" >/dev/null
      if [ ! $? == 0 ]; then
         error "'pdo_mysql' extension for PHP not found."
         notice_install_pdo_mysql
         php_good=no
      fi

      # Check for php5-mysql
      $php_cmd --info|grep "^gd\$" >/dev/null
      if [ ! $? == 0 ]; then
         error "'gd' extension for PHP not found."
         notice_install_gd
         php_good=no
      fi
   fi

   # Check for mysqld
   msg "Checking MySQL version..."
   which mysqld >/dev/null
   if [ ! $? == 0 ]; then
      error "MySQL Server was not found."
      notice_install_mysql
      mysql_good=no
   else
      mysql_good=yes
      msg "... `mysqld --version 2>/dev/null | cut -d " " -f 4`"
   fi

   if [ $php_good == no ]; then
      return 1;
   fi
   if [ $mysql_good == no ]; then
      return 1;
   fi
   return 0;
}

get_timezone() {
   if [ -e /etc/timezone ]; then
      timezone=`cat /etc/timezone`
      return 0
   fi
   if [ -e /etc/sysconfig/clock ]; then
      . /etc/sysconfig/clock
      timezone=$ZONE
      return 0
   fi
   timezone=UTC
}

download_wordpress() {
   cd "$wpdev_root/_data/installers"
   # Download only if newer; use "content-disposition" filename
   wget -N --content-disposition http://wordpress.org/latest.zip
   # Always update timestamp even if no update
   touch `find wordpress-*.zip|sort -V -r|head -n 1` >/dev/null
}

update_wordpress() {
   mkdir -p "$wpdev_root/_data/installers"
   msg "Checking for latest WordPress installer in ./_data/installers..."
   cd "$wpdev_root/_data/installers"

   # See if any versions exist
   find wordpress-*.zip >/dev/null
   if [ ! $? == 0 ]; then
      msg "WordPress not found. Downloading latest version..."
      download_wordpress
      # Make sure any version was downloaded
      find wordpress-*.zip >/dev/null
      if [ ! $? == 0 ]; then
         error "Failed to download WordPress installer."
         return 1
      fi
   else
      latest=`find wordpress-*.zip|sort -V -r|head -n 1`
      latest_ts=`stat -c %Y $latest`
      now_ts=`date +%s`
      if (( latest_ts < (now_ts - 86400) )); then
         msg "Last check was more than 1 day ago; checking server and downloading if newer..."
         download_wordpress
      fi
   fi

   # Find latest downloaded version
   latest=`find wordpress-*.zip|sort -V -r|head -n 1`

   # Write latest version to config file
   if [ "$latest" != "$WP_VERSION" ]; then
      set_config_item WP_VERSION $latest
   fi

   msg "... using version: $latest"
}

mysql_setup() {
   if [ -f "$wpdev_root/_data/databases/ibdata1" ]; then
      return 0
   fi

   notice "MySQL not setup; copying initial MySQL data..."
   cd "$wpdev_root/_data"
   tar zxf "$wpdev_root/_system/templates/databases-start.tar.gz"

   notice "... Setting random MySQL password. Starting MySQL..."

   new_password=`cat /dev/urandom | tr -dc "a-zA-Z0-9" | dd bs=25 count=1 2>/dev/null`
   set_config_item DB_PASSWORD $new_password

   # Run temporary copy of MySQL to set password
   mysqld \
      --datadir="$wpdev_root/_data/databases" \
      --bind-address=127.0.0.1 \
      --port=$MYSQL_PORT \
      --socket="$wpdev_root/_data/mysql.socket" \
      --pid-file="$wpdev_root/_data/mysql.pid" \
      >>"$wpdev_root/_data/mysql.log" 2>>"$wpdev_root/_data/mysql.log" &

   sleep 1
   rep=0
   while [ ! -S "$wpdev_root/_data/mysql.socket" ] ;
   do
      sleep 1
      let "rep+=1"
      if [ $rep -gt 10 ]; then
         error "MySQL failed to start."
         cat "$wpdev_root/_data/mysql.log"
         return 1
      fi
   done

   # 6fwv... is the initial password
   mysqladmin --host=127.0.0.1 --port=$MYSQL_PORT \
      --user=root \
      --password=6fwv4IT3RnYgMRACdq9XWvLsb \
      password $new_password
   result=$?

   msg "... Stopping MySQL..."
   mysqlpid=`cat "$wpdev_root/_data/mysql.pid"`
   kill $mysqlpid

   if [ ! $result == 0 ]; then
      error "... Failed to set new MySQL password."
      mysqlpid=`cat "$wpdev_root/_data/mysql.pid"`
      return 1
   fi

   return 0
}

# ----------------------------------------------------------------------
php_start() {
   msg "Starting PHP..."

   : >"$wpdev_root/_data/php.log" # ceate/truncate the log file

   $php_cmd \
      --server localhost:$WEB_PORT \
      --docroot "$wpdev_root" \
      --define post_max_size=$MAX_UPLOAD \
      --define upload_max_filesize=$MAX_UPLOAD \
      "$wpdev_root/router.php" \
      >>"$wpdev_root/_data/php.log" 2>>"$wpdev_root/_data/php.log" &
   phppid=$!

   sleep 2
   if kill -s 0 $phppid; then
      msg "... PHP started on http://localhost:$WEB_PORT/ ."
      return 0
   else
      error "... PHP failed to start."
      cat "$wpdev_root/_data/php.log"
      return 1
   fi
}
php_stop() {
   msg "Stopping PHP..."
   kill $phppid
}

# ----------------------------------------------------------------------
mysql_start() {
   msg "Starting MySQL..."

   : >"$wpdev_root/_data/mysql.log" # ceate/truncate the log file

   mysqld \
      --datadir="$wpdev_root/_data/databases" \
      --bind-address=127.0.0.1 \
      --port=$MYSQL_PORT \
      --socket="$wpdev_root/_data/mysql.socket" \
      --pid-file="$wpdev_root/_data/mysql.pid" \
      >>"$wpdev_root/_data/mysql.log" 2>>"$wpdev_root/_data/mysql.log" &

   sleep 1
   rep=0
   while [ ! -S "$wpdev_root/_data/mysql.socket" ] ;
   do
      sleep 1
      let "rep+=1"
      if [ $rep -gt 10 ]; then
         error "... MySQL failed to start."
         cat "$wpdev_root/_data/mysql.log"
         return 1
      fi
   done

   msg "... MySQL started on port $MYSQL_PORT."
   return 0
}

mysql_stop() {
   mysqlpid=`cat "$wpdev_root/_data/mysql.pid"`
   msg "Stopping MySQL..."
   kill $mysqlpid
}

# ----------------------------------------------------------------------
cleanup() {
   php_stop
   mysql_stop
   return 0
}
control_c() {
   echo
   cleanup
   exit $?
}

# ----------------------------------------------------------------------

load_config

url="file://$wpdev_root/docs/instant wordpress-dev.html"
url=$(sed -e 's/ /+/g' <<< "$url")
bold "Don't forget to read the manual:"
bold "$BLUE$url"
echo ""

check_requirements || exit $?
update_wordpress || exit $?
mysql_setup || exit $?
php_start || exit $?
mysql_start || {
   x=$?
   php_stop
   exit $x
}
echo ""
bold "Access your Instant WordPress Dev server at: ${BLUE}http://localhost:$WEB_PORT/"
bold "Type CTRL-C to shut down Instant WordPress Dev when you are finished working."
echo ""
trap control_c SIGINT
while true; do read x; done
