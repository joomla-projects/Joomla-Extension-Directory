Joomla! Extensions Directory
============================

Build Status
---------------------
| Drone-CI                                                                                                                                                                  |  PHP           |
|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------|  ------------- |
| [![Build Status](http://ci.joomla.org/api/badges/joomla-projects/Joomla-Extension-Directory/status.svg)](http://ci.joomla.org/joomla-projects/Joomla-Extension-Directory) | [![PHP](https://img.shields.io/badge/PHP-V8.1.0-green)](https://www.php.net/) |

The component which powers the Joomla Extensions Directory (extensions.joomla.org).

Original Specifications Document from 2020: https://drive.google.com/file/d/1G4M-5jAABBIUEq3gLE9W6WxMcgZxVJYx/view?usp=sharing

Now moved to Joomla 5 development - Installation on Joomla 4 will FAIL.

Build Instructions
------------------
**On Windows (making sure you have php and composer installed)**d:d

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
Install as an Extension into a clean Joomla 5 installation. Do not create any users other than the admin.

Once you see 'Installation of the package was successful.'

* Click to go to System and Plugins and enable 'Sample Data - JED'
* Click to go to the Home Dashboard
* Click Install next to JED Sample Data (this will install sample extensions/reviews/categories/tickets and users however firstly it will move your admin user to id=5 so that you can still login!) Once this update has taken place the site is likely to log you out. Just relogin as your admin user and you should be fine.

In Admin visit JED and view Tickets, Vulnerable Items, Categories and Extensions

**Instructions for Front end menus.**

In the backend:
* Click on Components -> JED -> Tickets
* At the top of the screen choose the Config menu and select 'Setup front end demo menu' and then Click the button GO (this will create a front end menu)
* Go to System -> Site Modules and click to edit 'Main Menu'.
* From the Select Menu dropdown choose 'Joomla Extension Directory Demo Menu' and hit save and close.

**Instructions for Front end testing.**

As part of the Sample Data installation a new user is created -
**testuserj5final** - with a password of - **Who0CaresF0rPasswords**

All of the sample front end data, is tied to this account so you can view previous tickets, previous VEL entries etc.
