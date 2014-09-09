IBMiToolkit
==========

travis-ci status coming soon, which will indicate stability of the master branch!

For production systems please do not use the master branch.  Instead use the latest 
[stable release](https://github.com/zendtech/IbmiToolkit/releases/latest).

This project was originally hosted at https://code.google.com/p/zend-ibmi-tk-cw/ 
where older versions still reside. As of Mar. 3, 2014 it is now maintained here.

Introduction
------------

This IBMiToolkit is a PHP-based front end to [XMLSERVICE](http://www.youngiprofessionals.com/wiki/XMLSERVICE).

Zend Server
-----------

XMLSERVICE and the IBMiToolkit are already shipped with Zend Server. But being 
open source they can also be downloaded, installed, and upgraded separately.

Autoloading
-----------

Versions larger than 1.6 use a classmap (a way to map classes to files for easier finding) 
to perform [autoloading](http://php.net/manual/en/language.oop5.autoload.php) so 
the user no longer needs to do it.  Therefore the IBMiToolkit may be used standalone, 
or may be pulled into a projects using [Composer](https://getcomposer.org/).

NOTE: The installation methods below are for versions greater than 1.6. Prior 
versions did not use Composer.

#### Standalone Method

1. Download the IBMiToolkit (tar.gz or zip) [stable release](https://github.com/zendtech/IbmiToolkit/releases/latest). 
Save downloaded file to desired location. (Ex.- /var/www/html or /usr/local/zend/var/apps/http/{sitename}/80/_docroot_/0/)

2. Unzip the content to desired location via terminal. (Example path used below will vary.)

    ```console
    $ cd /var/www/html/myproject/
    $ tar -xzvf 1.6.0.tar.gz
    ```

3. Install Composer (add composer.phar) to project location, along side the file 
composer.json, as outlined at https://getcomposer.org/download
    
4. Run Composer install via terminal to gain classmap autoloading.
    
    ```console
    $ php composer.phar install
    ```

5. Include the Composer generated autoloader into PHP application.
    
    ```php
    require 'vendor/autoload.php';
    ```

6. IBMiToolkit may now be used via namespaces, and as outlined at 
http://files.zend.com/help/Zend-Server-6-IBMi/zend-server.htm#php_toolkit_xml_service_functions.htm

#### Integrated Method

1. If an app already uses Composer simply update the composer.json 
as specified at https://packagist.org/packages/zendtech/ibmitoolkit
    
2. If Composer is not used in a project add it as with 3 above. More info can be 
found at https://getcomposer.org
    
3. Run Composer update if a project already has a composer.lock generated, or install if things are fresh.
    
    ```console
    $ php composer.phar update
    ```

    or

    ```console
    $ php composer.phar install
    ```

4. IBMiToolkit may now be used via namespaces, and as outlined at 
http://files.zend.com/help/Zend-Server-6-IBMi/zend-server.htm#php_toolkit_xml_service_functions.htm
