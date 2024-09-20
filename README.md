# LAMP for epicollect5

Epicollect5 server runs on Ubuntu 18 LTS & PHP 7.1

- install apache 2
  ```
  sudo apt-get update
  sudo apt-get -y install apache2

- enable mod_rewrite for Apache ()
  ```
  sudo a2enmod rewrite
- enable headers
  ```
  sudo a2enmod headers
- restart server
  ```
  sudo service apache2 restart
- install mysql (5.7+) secure installation

  https://www.digitalocean.com/community/tutorials/how-to-install-mysql-on-ubuntu-18-04

  "On Ubuntu 18.04, only the latest version of MySQL is included in the APT package repository by default. At the time
  of writing, that’s MySQL 5.7"

  ```
  sudo apt install mysql-server
  sudo mysql_secure_installation
- set root password for mysql
  ```
  ALTER USER 'root'@'localhost' IDENTIFIED BY '{_my_pwd}';
- install php 7.1
  ```
  sudo apt-get -y install php7.1 libapache2-mod-php7.1
- IMP: if it does not work, look here
  https://www.key2goal.com/article/install-multiple-php-73-72-71-ubuntu-1804-ubuntu-1604

- install php 7 zip extension.
  ```
  sudo apt-get install php7.1-zip 
- install php ldap extension (if needed).
  ```
  sudo apt-get install php-ldap
- install GD Library
  ```
  sudo apt-get install php7.1-gd
- install Imagick Library
  ```
  sudo apt-get install imagemagick
  sudo apt-get install php-imagick
- install XSendFile for downloading media
  ```
  sudo apt-get install libapache2-mod-xsendfile
- enable the module in .htaccess and http conf
  ```
  /etc/apache2/conf-available# touch xsend.conf
- add:
  ```
  XSendFile  On
  # XSendFile utilizes contents in your /storage/ directory.
  XSendFilePath   /var/www/html_prod/shared/storage

- enable it:
  ```
  sudo a2enconf xsend
- log into mysql and add epicollect user
  ```  
  CREATE USER 'epicollect_admin'@'localhost' IDENTIFIED BY 'strong_password';

  GRANT USAGE ON *.* TO 'epicollect_admin'@'localhost';
- create the epicollect5 database in mysql
  ```
  CREATE database epicollect5;
- grant privileges to the user on the db (see https://goo.gl/qu9SAO)
  ```
  GRANT ALL PRIVILEGES on epicollect5.* TO 'epicollect_admin'@'localhost';`
- Check users with
  ```
  SELECT User, Host, authentication_string FROM mysql.user;
- install composer (1.6.2)
  ```
  curl -O "https://getcomposer.org/download/1.6.2/composer.phar"
  chmod a+x composer.phar
  sudo mv composer.phar /usr/local/bin/composer
- Install missing packages for php 7.1
  ```
  sudo apt-get install php7.1-mbstring
  sudo apt-get install phpunit
  sudo apt-get install php-curl

- this is important, it will give PDO errors otherwise
  ```
  sudo apt-get install php-mysql
- check php -version, if not 7.1 do:
  ```
  sudo rm /etc/alternatives/php; sudo ln -s /usr/bin/php7.1 /etc/alternatives/php;

- look at Apache 000-default-config in `/etc/apache2/sites-available`

- set document root and directory
  ```
  ServerAdmin webmaster@localhost
  DocumentRoot /var/www/html/public/
  (this is to redirect to public)

  <Directory /var/www/>
    Options Indexes FollowSymLinks MultiViews
    AllowOverride All
    Order allow,deny
    allow from all
  </Directory>
- Set up redirection of http to https (do it later, add it to apache conf)
  ```
  RewriteEngine on
  RewriteCond %{SERVER_NAME} =five.epicollect.net
  RewriteRule ^ https://%{SERVER_NAME}%{REQUEST_URI} [END,QSA,R=permanent]
- restart server
  ```
  sudo service apache2 restart
- Imp: the robots.txt in root (/public) is to avoid Search Engines crawling the site
  ```
  User-agent: *
  Disallow: /
- Important: in the php.ini disable `op_cache` for development otherwise it will not update changes**

# Laravel for epicollect5

- Install Composer globally https://do.co/2SRRiF5
- Install Deployer https://deployer.org/, version 4.2.1 otherwise some Laravel 5.4 tasks are not defined,
  see https://github.com/deployphp/deployer/pull/1889
  ```
  curl -LO https://deployer.org/releases/v4.2.1/deployer.phar
    mv deployer.phar /usr/local/bin/dep
    chmod +x /usr/local/bin/dep
  ```

- increase memory_limit in Loaded Configuration File:         `/etc/php/7.1/cli/php.ini` (php --ini) to 1024M
- add a swap file (8GB at least)
  https://linuxize.com/post/how-to-add-swap-space-on-ubuntu-18-04/
- copy `deploy.php` into /var/www/html (or any root directory of your choice)
  ```
  sudo dep install production
- if it fails with out of memory errors, delete all folders and start fresh, increasing composer timeout from 300 secs
  to 5 minutes.
- if any error, install the missing PHP packages, like:
  ``` 
  sudo apt-get install php7.1-zip
  apt-get install php7.1-curl
  sudo apt-get install php7.1-xml` -> this always

- restart apache

## Forking

We provide this software as is, under MIT license, for the benefit and use of the community, however we are unable to
provide support for its use or modification.

You are not granted rights or licenses to the trademarks of the CGPS or any party, including without limitation the
Epicollect5 name or logo.
If you fork the project and publish it, please choose another name.





