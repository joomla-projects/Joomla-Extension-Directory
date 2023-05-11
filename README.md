Joomla! Extensions Directory
============================

Build Status
---------------------
| Drone-CI                                                                                                                                                                  |  PHP           |
|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------|  ------------- |
| [![Build Status](http://ci.joomla.org/api/badges/joomla-projects/Joomla-Extension-Directory/status.svg)](http://ci.joomla.org/joomla-projects/Joomla-Extension-Directory) | [![PHP](https://img.shields.io/badge/PHP-V8.1.0-green)](https://www.php.net/) |

The component which powers the Joomla Extensions Directory (extensions.joomla.org).

Original Specifications Document from 2020: https://drive.google.com/file/d/1G4M-5jAABBIUEq3gLE9W6WxMcgZxVJYx/view?usp=sharing

Build Instructions
------------------
**On Windows (making sure you have php and composer installed)**

In a command window run
* Clean-Windows.bat
* Build-Windows-a.bat
* Build-Windows-b.bat
* Build-Windows-c.bat

**On Linux (making sure you have php and composer installed)**

In shell run
* sh clean-linux.sh
* sh build-linux.sh

Look in the dist folder for pkg-jed-4.0.0.zip

Joomla Install Instructions
--
Install as an Extension into a clean Joomla 4 installation. Do not create any users other than the admin.

Once you see 'Installation of the package was successful.'

* Click to go to System and Plugins and enable 'Sample Data - JED'
* Click to go to the Home Dashboard
* Click Install next to JED Sample Data (this will install sample extensions/reviews/categories/tickets and users however firstly it will move your admin user to id=5 so that you can still login!) Once this update has taken place the site is likely to log you out. Just relogin as your admin user and you should be fine.

In Admin visit JED and view Tickets, Vulnerable Items, Categories and Extensions

Instructions for Front end menus - coming soon.
