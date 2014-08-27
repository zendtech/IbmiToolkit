IbmiToolkit
==========

travis-ci status coming soon!

Introduction
------------

For production systems please do not use the master branch.  For the latest, or 
a previous, stable releases please see [releases](https://github.com/zendtech/IbmiToolkit/releases).

This project was originally hosted at https://code.google.com/p/zend-ibmi-tk-cw/ 
where older versions still reside. As of Mar. 3, 2014 it is now maintained here.

This toolkit is a PHP-based front end to XMLSERVICE (http://www.youngiprofessionals.com/wiki/XMLSERVICE). 
Both parts of the toolkit are shipped with Zend Server. Being open source, they 
can also be downloaded, installed, and upgraded separately.

Zend Server
-----------

The toolkit is already pre-installed with Zend Server, so you may not need to install.

Installation
------------

This toolkit can be used standalone, or can be pulled into your current projects 
using Composer.

NOTE: The installation methods below are for versions greater than 1.6.0. Prior 
versions did not use Composer.

### Standalone

1. Download the toolkit from one of the stables [releases](https://github.com/zendtech/IbmiToolkit/releases).

2. Install Composer as outlined at https://getcomposer.org/download
    
3. Run Composer install to gain PSR-4 autoloading, for using the toolkit.
    
```console
$ php composer.phar install
```

4. Include the Composer generated autoloader in your application.
    
```php
require 'vendor/autoload.php';
```

5. Now the toolkit may be used via namespaces, and as outlined at http://files.zend.com/help/Zend-Server-6-IBMi/zend-server.htm#php_toolkit_xml_service_functions.htm

### Integrated

1. If your app already uses Composer you simply need to update the composer.json as specified at https://packagist.org/packages/zendtech/ibmitoolkit
    
2. If you do not have Composer in your project you will need to add it. More info can be found at https://getcomposer.org
    
3. Run Composer update, or install if things are fresh.
    
```console
$ php composer.phar update
```

4. Now the toolkit may be used via namespaces, and as outlined at http://files.zend.com/help/Zend-Server-6-IBMi/zend-server.htm#php_toolkit_xml_service_functions.htm

