PHP Toolkit for IBM i 
=====================

[![Build Status](https://travis-ci.org/zendtech/IbmiToolkit.svg?branch=master)](https://travis-ci.org/zendtech/IbmiToolkit)

For production systems please do not use the master branch.  Instead use the latest 
[stable release](https://github.com/zendtech/IbmiToolkit/releases/latest).

Introduction
------------

The PHP Toolkit for IBM i (Toolkit) is a PHP-based front end to [XMLSERVICE](http://www.youngiprofessionals.com/wiki/XMLSERVICE) that helps programmers call RPG and CL programs along with other native resources from within PHP. 

The Toolkit is open source and has been developed with help from Alan Seiden and the community. 

Discussion of the Toolkit takes place in GitHub Discussions:
https://github.com/zendtech/IbmiToolkit/discussions

Current Main Features:

- Ability to call RPG, CL, and COBOL
- Run interactive commands such as ‘wrkactjob’
- Transport-neutral, supporting DB2, ODBC, and HTTP, and others as needed
- Compatibility wrapper to execute Easycom syntax
- Support of all RPG parameter types, including data structures, packed decimal, and output parameters

XMLSERVICE and the IBM i Toolkit are already shipped with Zend Server and Seiden CommunityPlus+ PHP. But being 
open source they can also be downloaded, installed, and upgraded separately.

For examples, please visit:
https://github.com/zendtech/IbmiToolkit/tree/master/samples
