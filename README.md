IBMiToolkit
==========

[![Build Status](https://travis-ci.org/zendtech/IbmiToolkit.svg?branch=master)](https://travis-ci.org/zendtech/IbmiToolkit)

For production systems please do not use the master branch.  Instead use the latest 
[stable release](https://github.com/zendtech/IbmiToolkit/releases/latest).

Introduction
------------

The PHP Toolkit for IBM i (Toolkit) is a PHP-based front end to [XMLSERVICE](http://www.youngiprofessionals.com/wiki/XMLSERVICE) that helps programmers call RPG and CL programs along with other native resources from within PHP. 

The Toolkit is open source and has been developed with help from Alan Seiden and the community. 

Community discussion of the Toolkit happens at the ‘Club Seiden’ forum:
http://club.alanseiden.com/community/

Current Main Features:

- Ability to call RPG, CL, and COBOL
- Ability call IBM i native resources such as Spool Files, Data Areas, and System Values
- Run interactive commands such as ‘wrkactjob’
- Designed to used a choice of transports including DB2, ODBC , and HTTP
- Compatibility wrapper to execute Easycom syntax


Planned Features:

- More and better code samples 
- Run SQL
- Inline transport (not requiring DB2 connection)
- Improve usability of the API
- Optimizations for larger data sets
- More (to be added to GitHub issues)

XMLSERVICE and the IBMi Toolkit are already shipped with Zend Server. But being 
open source they can also be downloaded, installed, and upgraded separately.

Autoloading
-----------

Versions larger than 1.6 use a classmap (a way to map classes to files for easier finding) 
to perform [autoloading](http://php.net/manual/en/language.oop5.autoload.php) so 
the user no longer needs to do it.  Therefore the IBMiToolkit may be used standalone, 
or may be pulled into a projects using [Composer](https://getcomposer.org/).

NOTE: The installation methods below are for versions greater than 1.6. Prior 
versions did not use Composer.

Installation
------------

The methods outlined below are intended for Development environments, and possibly 
Testing and/or Staging environments. However, it is recommended to deploy a prepared 
package to a Production environment rather than use Composer.

#### Standalone Method

1. Download a IBMiToolkit (tar.gz or zip) [stable release](https://github.com/zendtech/IbmiToolkit/releases/latest). 
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
