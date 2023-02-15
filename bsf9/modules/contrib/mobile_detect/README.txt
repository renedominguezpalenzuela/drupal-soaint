CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Troubleshooting
 * Maintainers


INTRODUCTION
------------

This is a lightweight mobile detection based on the Mobile_Detect.php library,
which can be obtained from the GitHub repository.This module is intended to aid
developers utilizing mobile-first and responsive design techniques who also
have a need for slight changes for mobile and tablet users. An example would be
showing (or hiding) a block or content pane to a particular device.

 * For a full description of the module, visit the project page:
   https://drupal.org/project/mobile_detect

 * To submit bug reports and feature suggestions, or to track changes:
   https://drupal.org/project/issues/mobile_detect


REQUIREMENTS
------------

 * As noted, this module does not include the actual Mobile_Detect library.
   This should be downloaded or cloned from one of the links above and
   placed in:

   sites/all/libraries/Mobile_Detect
   or somewhere the Libraries API (if present) can find it, eg

   sites/default/libraries/Mobile_Detect
   sites/example.com/libraries/Mobile_Detect


INSTALLATION
------------

 * Install as you would normally install a contributed Drupal module. See:
   https://www.drupal.org/docs/8/extending-drupal-8/installing-modules
   for further information.

 * Once the module is installed and enabled, browse to the Status Report
   page (admin/reports/status) and confirm that the library is found. The PHP
   file should have a pathname that is similar to
   sites/all/libraries/Mobile_Detect/Mobile_Detect.php If you think everything
   is installed correctly, you may need to clear the Drupal caches
   (admin/config/development/performance). For testing purposes, the demo.php
   and unit tests included with the library can be deployed, but these should
   not exist on a live production server.

 * Installing using composer to download all libraries required (recommended)

          composer require drupal/mobile_detect


CONFIGURATION
-------------

 * The base module just provides a service for use in themes and other modules:

   $md = \Drupal::service('mobile_detect');
   $md->isMobile();
   $md->isTablet()

 * It adds common Twig extensions to work with html.twig templates:

   {% if is_mobile() %}
   {% if is_tablet() %}
   {% if is_device('iPhone') %}
   {% if is_ios() %}
   {% if is_android_ios() %}

   See the documentation for the Mobile_Detect library for more information.

   Note that the Mobile_Detect considers tablet devices as also being mobile
   devices. When you have both tablet and mobile device selection in use, it is
   best to place the tablet rules first. For example, when using with for Panel
   page selection rules, place the Table variant before the Mobile variant.


TROUBLESHOOTING
---------------

 * Problems with the actual Mobile_Detect library should be directed to the
   GitHub issue queue.

 * Problems with this module and sub-modules should be directed to the Drupal
   issue queue for this project.

  * The "Internal Page Cache" core module assumes that all pages served 
    to anonymous users will be identical, regardless of the implementation 
    of cache contexts.
 
    If you want to use the mobile_detect cache contexts to vary the content 
    served to anonymous users, "Internal Page Cache" must be disabled, 
    and the performance impact that entails incurred.

  * https://www.drupal.org/docs/drupal-apis/cache-api/cache-contexts

MAINTAINERS
-----------

Current maintainers:
 * Darryl Norris (darol100) - https://www.drupal.org/u/darol100
 * Matthew Donadio (mpdonadio) - https://www.drupal.org/u/mpdonadio
 * Sora_tm - https://www.drupal.org/u/sora_tm
 * Viktor Vilyus (VVVi) - https://www.drupal.org/u/vvvi
 * Matt Chapman (chapabu) - https://www.drupal.org/u/chapabu
 * Antonio Martinez (nonom) - https://www.drupal.org/u/nonom

