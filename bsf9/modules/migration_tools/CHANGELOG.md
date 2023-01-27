migration_tools 8.x-2.x  ** - ** - ****
-----------------------------------------------


migration_tools 8.x-2.2 11-20-2019
-----------------------------------------------
* Issue #3095810 Redirect: Replace %20 with space on source.
https://www.drupal.org/project/migration_tools/issues/3095810


migration_tools 8.x-2.1 10-15-2019
-----------------------------------------------
* Issue #3082237: Cleanup depricated functions.
  https://www.drupal.org/project/migration_tools/issues/3082237
* CS cleanup of Url and Redirect.
* Issue #3069804: Redirect: Check is failing to find existing redirect.
  https://www.drupal.org/project/migration_tools/issues/3069804
* Issue #3060323: Redirect:Media Provide option to redirect to file rather than media entity.
  https://www.drupal.org/project/migration_tools/issues/3060323
* Issue #3067679: Add csv migration example
  https://www.drupal.org/project/migration_tools/issues/3067679
* Issue #3067637: Fix host rewriting of hrefs does not respect race condition.
  https://www.drupal.org/project/migration_tools/issues/3067637
* Issue #3064623 by benjf: Minor bug fixes
  https://www.drupal.org/project/migration_tools/issues/3064623
* Issue #3056992 DomModifier: convert image link to media token via redirect
  lookup.
  https://www.drupal.org/project/migration_tools/issues/3056992
* Move obtainer out of examples and in to obtainer.
* Issue #3056992: DomModifier: convert image link to media token
  https://www.drupal.org/project/migration_tools/issues/3056992
  Part A of this done.  Converting based on migrate_map lookup.
* Issue #3056452 by beeyayjay: Obtainer:obtainTitleNoCaseChange needed.
  https://www.drupal.org/project/migration_tools/issues/3056452
* Issue #3060024: Allow Migration Tools to not require source or dom operations.
  https://www.drupal.org/project/migration_tools/issues/3060024
* Issue #3056948: PortURL rewriting to D8
  https://www.drupal.org/project/migration_tools/issues/3056948
* Issue #3032411: D8-Port redirect creation on import.
  https://www.drupal.org/project/migration_tools/issues/3032411
* Issue #3055532: Obtainer: Need a finder to grab a sibling by index and selector
  https://www.drupal.org/project/migration_tools/issues/3055532
* Issue #3048397: Create a Message event for customized error handling.
  https://www.drupal.org/project/migration_tools/issues/3048397
* Issue #3055283: Create new DomOperation: removeMatchAndNextSibling
  https://www.drupal.org/project/migration_tools/issues/3055283
* Obtainer: Add an obtainer to handle plain text without stripping out line
  breaks.  A basic set of finders and pluckers that default to html in order to
  not lose the line breaks in QueryPath.
  https://www.drupal.org/project/migration_tools/issues/3044998
* Operations: Add process for setting curl header options.
  https://www.drupal.org/project/migration_tools/issues/3031705
* Operations: Fix notice undefined index:arguments.
  https://www.drupal.org/project/migration_tools/issues/3012485
* DomModifier: Add changeHtmlContents modifier.
  https://www.drupal.org/project/migration_tools/issues/3015381


migration_tools 8.x-2.0-alpha2 04-09-2018
-----------------------------------------------


* Creates source (HTML) operations and DOM (QueryPath) operations, which allow for ordering of operations mixed with field retrieval     from the DOM.
* Adds a DOM migrate_plus data_parser_plugin, which allow you to extract (chunk) URLs from an HTML page into rows for migration.
* Adds ability to use jobs to set a variable, then use that variable as a dynamic argument in another job's arguments
* Adds replaceString modifier.
* Adds HREF functions ObtainLink/ObtainFileLink
* Updates examples


migration_tools 8.x-2.x 03-15-2018
-----------------------------------------------

* Issue #2952547: Initial port to Drupal 8. Obtainers, Modifiers and string
  cleanup as well as an html source parser has been ported to work with a yml
  migration file.

migration_tools 7.x-2.8  November 2, 2017
-----------------------------------------------
* Issue #2920601: Fix Html Source Parser introduces character encoding issues.

migration_tools 7.x-2.7  October 24, 2017
-----------------------------------------------
* Issue #2918411: Installing newest version of markdown causes fatal error.

migration_tools 7.x-2.6  October 24, 2017
-----------------------------------------------
* Issue 2918274: Class 'MigrationTools\SourceParser\Exception' not found
* Minor documentation and logging clean-up.


migration_tools 7.x-2.5  July 29, 2016
-----------------------------------------------
* Issue #2775087: Add migration examples
* Issue #2775065: Remove outdated examples.
* Issue #2775033: Update SourceParser Examples.
* Issue #2775031: Make addModifier return $this so that they can be chained
* Issue #2774963: Make addSearch return $this so that they can be chained.
* Issue #2773835: Add ObtainArray support to Obtainers


migration_tools 7.x-2.4  July 27, 2016
-----------------------------------------------
* Issue #2770953: Add function to remove empty tables.
* Issue #2772389 Add Modifier class to stack up html modifiers to run in cleanQueryPathHtml()
  in the SourceParser.


migration_tools 7.x-2.3  July 15, 2016
-----------------------------------------------
* Fix Error if using QueryPath version that does not support size()/count().
* Add findSelectorNSeparator to ObtainHtml.
* Add findSelector to ObtainHtml.
* Add reduceDuplicateBr() to StringTools.
* Removed duplicate internal Table cell plucker.
* Renamed pluckTableContents to pluckTableCellContents to make it more explicit.
* Renamed internal method pluckTableCell to extractTableCell so to keep it
  as an internal method not a plucker.
* Fix false positives on scanning for JS redirects.
* Issue #2755321: Create a set of source tools to handle migrating from an array of URLs.
* Add drush command mt-generate-redirects-list [filename] to read a list of urls
  and turn it into a list of redirects for any that were detected in html or
  server-side.
* Issue #2753779: Add file data to row properties
* Issue #2749377: Rewiting an empty href results in bad link
* Fixed bug where longdescription paths were not getting checked.
* Add javascript src rewriting and flash attribute rewriting.
* Adjust ObtainTitle to better remove whitespaces in title.
* Bugfix ObtainDate where a short date is wrongly rejected.
* Add more verbose output using: drush vset migration_tools_drush_debug 2
* Add alias reporting.
* Fix bug in Url::generateDestinationUriAlias().


migration_tools 7.x-2.2  May 31, 2016
-----------------------------------------------
* Move instance of SourceParser from $row to migration $this.
* Fix misnamed variable in link re-writing methods.
* Add QpHtml::removeComment()
* Move some basic cleaning calls into SourceParser\HtmlBase.


migration_tools 7.x-2.1  May 27, 2016
-----------------------------------------------
* Add stub class for Source\Url for when the source is live URLs.
* Minor improvements to Meta Redirect detection.
* Add redirect detection and handling to Migration\HtmlFileBase.
  Relocated some handling between Base and HtmlFileBase.
* Add CheckFor isSkipAndRedirect to allow for skipping and redirecting.
* Improvements to ObtainDate.
* Add OrganicGroups class for tools to handle OG issues.
* Adjust params on CheckFor isInPath isSkipFile to make them consistent in
  param order of needle, haystack.
* Add constructor to Url.php to create pathing object and some refactoring to
  use and support the new pathing object.
* Add Migration\HtmlFileBase class.
* Improved URL/URI rewriting method for page href, files and img src.
* Add Message::makeSummary()
* Align drush command terms.
* Improve html redirect destination verification.
* Add Message::makeSkip().
* Completed detection of html and javascript redirects.
* Connect Obtainer classes and add Obtainer\Job
* Add SourceParser classes using Obtainer
* Add Migration\Base class.
* Add sourcer class to load html files from a local directory
  https://www.drupal.org/node/2709651
* Add admin field for handling migration source location.
* Modified settings location to reside with other Migrate settings.
* Modified method of hiding the Migrate settings.
* PSR-4 autoloader added to autoload classes
* Renaming and moving of classes to support autoloading and namespacing.
* Stub for redirect detection.
* Destination URI validation.
* Add migration-tools-html-file-list drush utility command.
* Add helper method to URL class to check for default (index) files.

migration_tools 7.x-1.x  April 14, 2016
-----------------------------------------------
The 1.x branch is no longer being maintained.  The 7.x-2.x branch is now active.
* Consolidate methods for cleaning node titles.
* Add CHANGELOG.md
* Moved URL and redirect related methods into UrlTools.inc


migration_tools 7.x-1.0-alpha2  before April 13, 2016
-----------------------------------------------
* Created project from pieces used to migrate other sites.  Things that are
  currently not connected to the module's code are in the examples directory.
