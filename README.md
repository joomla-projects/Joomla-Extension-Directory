Joomla! Extensions Directory
============================

Build Status
---------------------
| GitHub Actions                                                                                                                                                                                    |  PHP           |
|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|  ------------- |
| [![CI](https://github.com/joomla-projects/Joomla-Extension-Directory/actions/workflows/ci.yml/badge.svg)](https://github.com/joomla-projects/Joomla-Extension-Directory/actions/workflows/ci.yml) | [![PHP](https://img.shields.io/badge/PHP-V8.1.0-green)](https://www.php.net/) |

The component which powers the Joomla Extensions Directory (extensions.joomla.org).

Original Specifications Document from 2020: https://drive.google.com/file/d/1G4M-5jAABBIUEq3gLE9W6WxMcgZxVJYx/view?usp=sharing

Now moved to Joomla 5 development - Installation on Joomla 4 will FAIL.

Build Instructions
------------------
Making sure you have PHP and Composer installed, you will need to apply the following patch to robo:
https://github.com/consolidation/robo/pull/1185 . Subsequently, run `./vendor/bin/robo build`

Look in the dist folder for pkg-jed-4.0.0.zip

Joomla Install Instructions
--
Install as an Extension into a clean Joomla 5 installation. Do not create any users other than the admin.

Once you see 'Installation of the package was successful.'

* Click to go to System and Plugins and enable 'Sample Data - JED'
* Click to go to the Home Dashboard
* Click Install next to JED Sample Data (this will install sample extensions/reviews/categories/tickets and users however firstly it will move your admin user to id=5 so that you can still login!) Once this update has taken place the site is likely to log you out. Just relogin as your admin user and you should be fine.

In Admin visit JED and view Tickets, Vulnerable Items, Categories and Extensions

To get the Template:
* Install Joomla 4 template from the templatework/jtemplate_4.0.9_jed folder (jtemplate_4.0.9_jedcustom.zip).
* Goto System / Site Template Styles and click on round circle under default for Joomla - Default template.

**Instructions for Front end testing.**

As part of the Sample Data installation a new user is created -
**testuserj5final** - with a password of - **Who0CaresF0rPasswords**

All of the sample front end data, is tied to this account so you can view previous tickets, previous VEL entries etc.
