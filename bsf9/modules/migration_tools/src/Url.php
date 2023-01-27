<?php

namespace Drupal\migration_tools;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Media\MediaInterface;
use Drupal\migrate\MigrateException;
use Drupal\node\Entity\Node;

/**
 * Class Url.
 *
 * In migrations it is easy to get lost in all the pathing related
 * information.  This list should help designate what is what in a migration.
 * Notice that they are all namespaced to ->pathing____ to make it easy to find
 * them and to avoid collisions with migration source data.
 *
 * $migration->pathingLegacyDirectory  [oldsite]
 * $migration->pathingLegacyHost [https://www.oldsite.com]
 * $migration->pathingRedirectNamespace [redirect-oldsite]
 * $migration->pathingSectionSwap
 *   [array(‘oldsite/section’ => ‘swapped-section-a’)]
 * $migration->pathingSourceLocalBasePath [/var/www/migration-source]
 *
 * $row->fileId [/oldsite/section/blah/index.html]
 * $row->pathing->namespacedUri [redirect-oldsite/section/blah/index.html]
 * $row->pathing->legacySection [section] or [section/sub-section]
 * $row->pathing->legacyUrl [https://www.oldsite.com/section/blah/index.html]
 * $row->pathing->destinationUriAlias [swapped-section-a/blah/title-based-thing]
 * $row->pathing->destinationUriRaw [node/123]
 * $row->pathing->redirectSources [Array of source NamespacedUri's for creating
 * redirects in complete().
 * $this->pathing->redirectDestination [any valid url, drupal path, drupal uri.]
 */
class Url {

  /**
   * Instantiates a pathing object to reside in a $row at $row->pathing.
   *
   * @param string $file_id
   *   The file ID of the row.
   *   [/oldsite/section/blah/index.html].
   * @param string $legacy_migration_source_path
   *   The legacy directory and optional sub-directories of the source file
   *   within 'migration-source'.
   *   [oldsite] or [oldsite/section/].
   * @param string $legacy_host
   *   The host of the source content.
   *   [https://www.oldsite.com].
   * @param string $redirect_namespace
   *   The base path in Drupal to uses in the redirect source source.
   *   [redirect-oldsite].
   * @param array $section_swap
   *   An array or path sections to swap if the location of the source content
   *   is going to be different from the location of the migrated content.
   *   [array(‘oldsite/section’ => ‘new-section’)].
   * @param string $source_local_base_path
   *   The environment base path to where the legacy files exist.
   *   [/var/www/migration-source].
   */
  public function __construct($file_id, $legacy_migration_source_path, $legacy_host, $redirect_namespace, array $section_swap, $source_local_base_path) {
    // Establish the incoming properties.
    $this->fileId = $file_id;
    $legacy_migration_source_path = ltrim($legacy_migration_source_path, '/');
    $directories = explode('/', $legacy_migration_source_path);
    $this->legacyDirectory = array_shift($directories);
    $this->legacySection = implode('/', $directories);
    $this->legacyHost = $legacy_host;
    $this->redirectNamespace = $redirect_namespace;
    $this->sectionSwap = self::drupalizeSwapPaths($section_swap);
    $this->sourceLocalBasePath = $source_local_base_path;
    $this->redirectSources = [];

    // Build the items we can build at this time.
    $this->generateNamespacedUri();
    $this->generateLegacyUrl();
    $this->destinationSection = (!empty($this->sectionSwap[$this->legacySection])) ? $this->sectionSwap[$this->legacySection] : $this->legacySection;

    // Create the placeholders for what might come later.
    $this->destinationUriAlias = '';
    $this->destinationUriRaw = '';
    $this->redirectDestination = '';
  }

  /**
   * Alter a path to remove leading and trailing slashes.
   *
   * @param string $path
   *   A URI path.
   *
   * @return string
   *   The drupalized path.
   */
  public static function drupalizePath($path) {
    return trim($path, '/ ');
  }

  /**
   * Alter a swapaths to remove leading and trailing slashes.
   *
   * @param array $swap_paths
   *   An array of key => value pairs where both key and values are paths.
   *
   * @return array
   *   The array with leading and trailing slashes trimmed from keys and values.
   */
  public static function drupalizeSwapPaths(array $swap_paths) {
    $new_paths = [];
    foreach ($swap_paths as $key => $value) {
      $key = self::drupalizePath($key);
      $value = self::drupalizePath($value);
      $new_paths[$key] = $value;
    }
    return $new_paths;
  }

  /**
   * Take a legacy uri, and map it to an alias.
   *
   * @param string $namespaced_legacy_uri
   *   The namespaced URI from the legacy site ideally coming from
   *   $row->pathing->namespacedUri
   *   ex: redirect-oldsite/section/blah/index.html
   *   redirect-oldsite/section/blah/index.html?foo=bar.
   * @param string $language
   *   Language.
   *
   * @return string
   *   The Drupal alias redirected from the legacy URI.
   *   ex: swapped-section-a/blah/title-based-thing
   */
  public static function convertLegacyUriToAlias($namespaced_legacy_uri, $language = LANGUAGE_NONE) {
    // @todo D8 Refactor
    // Drupal paths never begin with a / so remove it.
    $namespaced_legacy_uri = ltrim($namespaced_legacy_uri, '/');
    // Break out any query.
    $query = parse_url($namespaced_legacy_uri, PHP_URL_QUERY);
    $query = (!empty($query)) ? self::convertUrlQueryToArray($query) : [];
    $original_uri = $namespaced_legacy_uri;
    $namespaced_legacy_uri = parse_url($namespaced_legacy_uri, PHP_URL_PATH);

    // Most common drupal paths have no ending / so start with that.
    $legacy_uri_no_end = rtrim($namespaced_legacy_uri, '/');

    $redirect = redirect_load_by_source($legacy_uri_no_end, $language, $query);
    if (empty($redirect) && ($namespaced_legacy_uri != $legacy_uri_no_end)) {
      // There is no redirect found, lets try looking for one with the path /.
      $redirect = redirect_load_by_source($namespaced_legacy_uri, $language, $query);
    }
    if ($redirect) {
      $nid = str_replace('node/', '', $redirect->redirect);
      // Make sure we are left with a numeric id.
      if (is_int($nid) || ctype_digit($nid)) {
        $node = Node::load($nid);
        if ((!empty($node)) && (!empty($node->path)) && (!empty($node->path['alias']))) {
          return $node->path['alias'];
        }
      }

      // Check for language other than und, because the aliases are
      // intentionally saved with language undefined, even for a spanish node.
      // A spanish node, when loaded does not find an alias.
      if (!empty($node->language) && ($node->language != LANGUAGE_NONE)) {
        // Some other language in play, so lookup the alias directly.
        $path = url($redirect->redirect);
        $path = ltrim($path, '/');
        return $path;
      }

      if ($node) {
        $uri = entity_uri("node", $node);
        if (!empty($uri['path'])) {
          return $uri['path'];
        }
      }
    }

    // Made it this far with no alias found, return the original.
    return $original_uri;
  }

  /**
   * Generates a legacy website-centric URL for the source row.
   *
   * @param string $pathing_legacy_directory
   *   The directory housing the migration source.
   *   ex: If var/www/migration-source/oldsite, then 'oldsite' is the directory.
   * @param string $pathing_legacy_host
   *   The scheme and host of the original content.
   *   ex: 'https://www.oldsite.com'.
   *
   * @var string $this->legacyUrl
   *   Created property.  The location where the legacy page exists.
   *   ex: https://www.oldsite.com/section/blah/index.html
   */
  public function generateLegacyUrl($pathing_legacy_directory = '', $pathing_legacy_host = '') {
    // Allow the parameters to override the property if provided.
    $pathing_legacy_directory = (!empty($pathing_legacy_directory)) ? $pathing_legacy_directory : $this->legacyDirectory;
    $pathing_legacy_host = (!empty($pathing_legacy_host)) ? $pathing_legacy_host : $this->legacyHost;
    $uri = ltrim($this->fileId, '/');
    // Swap the pathing_legacy_directory for the $pathing_legacy_host.
    $url = str_replace($pathing_legacy_directory, $pathing_legacy_host, $uri);
    $this->legacyUrl = $url;
  }

  /**
   * Generates a drupal-centric Alias for the source row.
   *
   * @param string $pathing_section_swap
   *   An array of sections to replace
   *   ex: array('oldsite/section' => 'new-section')
   * @param string $title
   *   The title of the node or any other string that should be used as the
   *   last element in the alias.
   *   ex: '2015 A banner year for corn crop'.
   *
   * @throws MigrateException
   *   If pathauto is not available to process the title string.
   *
   * @return string
   *   A drupal ready alias based on its old location mapped to its new location
   *   and ending with the title string.
   *   ex: new-section/2015-banner-year-corn-crop
   *
   * @var string $this->legacyUrl
   *   Created property: The location where the legacy page exists.
   *   ex: new-section/2015-banner-year-corn-crop
   */
  public function generateDestinationUriAlias($pathing_section_swap, $title) {
    // @todo D8 Refactor
    // Allow the parameter to override the property if provided.
    $pathing_section_swap = (!empty($pathing_section_swap)) ? $pathing_section_swap : $this->sectionSwap;

    $directories = self::extractPath($this->fileId);
    $directories = ltrim($directories, '/');
    // Swap any sections as requested.
    $directories = str_replace(array_keys($pathing_section_swap), array_values($pathing_section_swap), $directories);

    // Remove the legacy directory if it is still present.
    $directories = explode('/', $directories);
    if ($directories[0] === $this->legacyDirectory) {
      array_shift($directories);
    }
    $directories = implode('/', $directories);

    // Attempt to process the title.
    if (module_load_include('inc', 'pathauto')) {
      $path_title = pathauto_cleanstring($title);
      $this->destinationUriAlias = "{$directories}/{$path_title}";
      $this->destinationUriAlias = pathauto_clean_alias($this->destinationUriAlias);
      // The property has been set, but return it in case assignment is desired.
      return $this->destinationUriAlias;
    }
    else {
      // Fail migration because the title can not be processed.
      Message::make('The module @module was not available to process the title.', ['@module' => 'pathauto'], Message::ERROR);
      throw new MigrateException();
    }
  }

  /**
   * Convert a relative URI from a page to an absolute URL.
   *
   * @param string $href
   *   Full URL, relative URI or partial URI. Ex:
   *   ../subsection/index.html,
   *   /section/subsection/index.html,
   *   https://www.some-external-site.com/abc/def.html.
   *   https://www.this-site.com/section/subsection/index.html.
   * @param string $base_url
   *   The location where $rel existed in html. Ex:
   *   https://www.oldsite.com/section/page.html.
   * @param string $destination_base_url
   *   Destination base URL.
   *
   * @return string
   *   The relative url transformed to absolute. Ex:
   *   https://www.oldsite.com/section/subsection/index.html,
   *   https://www.oldsite.com/section/subsection/index.html,
   *   https://www.some-external-site.com/abc/def.html.
   *   https://www.this-site.com/section/subsection/index.html.
   */
  public static function convertRelativeToAbsoluteUrl($href, $base_url, $destination_base_url) {
    $destination_base_url = trim($destination_base_url, '/');
    if ((parse_url($href, PHP_URL_SCHEME) != '') || self::isOnlyFragment($href)) {
      // $href is already a full URL or is only a fragment (onpage anchor)
      // No processing needed.
      return $href;
    }
    else {
      // Could be a faulty URL.
      $href = self::fixSchemelessInternalUrl($href, $destination_base_url);
    }

    $parsed_base_url = parse_url($base_url);
    $parsed_href = parse_url($href);
    // Destroy base_url path if relative href is root relative.
    if ($parsed_href['path'] !== ltrim($parsed_href['path'], '/')) {
      $parsed_base_url['path'] = '';
    }

    // Make the Frankenpath.
    $path = (!empty($parsed_base_url['path'])) ? $parsed_base_url['path'] : '';
    // Cut off the file.
    $path = self::extractPath($path);

    // Join it to relative path.
    $path = "{$path}/{$parsed_href['path']}";

    // Replace '//' or '/./' or '/foo/../' with '/' recursively.
    $re = ['#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#'];
    for ($n = 1; $n > 0; $path = preg_replace($re, '/', $path, -1, $n)) {
    }

    // The $path at this point should not contain '../' or it would indicate an
    // unattainable path.
    if (stripos($path, '../') !== FALSE) {
      // We have an unattainable path like:
      // 'https://oldsite.com/../blah/index.html'
      $message = 'Unable to make absolute URL of path: "@path" on page: @page.';
      $variables = [
        '@path' => $path,
        '@page' => $base_url,
      ];
      Message::make($message, $variables, Message::ERROR, 2);
    }

    // Make sure the query and fragment exist even if they are empty.
    $parsed_href['query'] = (!empty($parsed_href['query'])) ? $parsed_href['query'] : '';
    $parsed_href['fragment'] = (!empty($parsed_href['fragment'])) ? $parsed_href['fragment'] : '';

    // Build the absolute URL.
    $absolute = [
      'scheme' => $parsed_base_url['scheme'],
      'host' => $parsed_base_url['host'],
      'path' => $path,
      'query' => $parsed_href['query'],
      'fragment' => $parsed_href['fragment'],
    ];

    // Absolute URL is ready.
    return self::reassembleURL($absolute, $destination_base_url);
  }

  /**
   * Checks to see if an element is used as an image map.
   *
   * @param object $qp_element
   *   A Query{ath element, resulting from a find.
   *
   * @return bool
   *   TRUE if it is an image map.
   *   FALSE otherwise.
   */
  public static function isImageMap($qp_element) {
    $is_map = FALSE;
    // If this attribute is present, its a server side map.
    $ss_map = $qp_element->attr('ismap');
    // If this attribute is present, its a client side map.
    $use_map = $qp_element->attr('usemap');
    if ((!empty($ss_map)) || (!empty($use_map))) {
      $is_map = TRUE;
    }

    return $is_map;
  }

  /**
   * Examines an uri and evaluates if it is an image.
   *
   * @param string $uri
   *   A uri.
   *
   * @return bool
   *   TRUE if this is an image uri, FALSE if it is not.
   */
  public static function isImageUri($uri) {
    if (preg_match('/.*\.(jpg|gif|png|jpeg)$/i', $uri) !== 0) {
      // Is an image uri.
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Determines if a url is relative or absolute.
   *
   * @param string $url
   *   A url either relative or absolute.
   *
   * @return bool
   *   TRUE if relative url.
   *   FALSE if absolute url.
   */
  public static function isRelativeUrl(string $url) {
    $url_parts = parse_url($url);
    if ((!empty($url_parts['scheme'])) && (!empty($url_parts['host']))) {
      // It is an absolute url.
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Fixes anchor links to PDFs so that they work in IE.
   *
   * Specifically replaces anchors like #_PAGE2 and #p2 with #page=2.
   *
   * @param object $query_path
   *   The QueryPath object with HTML markup.
   *
   * @see http://www.adobe.com/content/dam/Adobe/en/devnet/acrobat/pdfs/pdf_open_parameters.pdf
   */
  public static function fixPdfLinkAnchors($query_path) {
    $anchors = $query_path->find('a');
    foreach ($anchors as $anchor) {
      $url = $anchor->attr('href');
      $contains_pdf_anchor = preg_match('/\.pdf#(p|_PAGE)([0-9]+)/i', $url, $matches);
      if ($contains_pdf_anchor) {
        $old_anchor = $matches[1];
        $page_num = $matches[3];
        $new_anchor = 'page=' . $page_num;
        $new_url = str_replace($old_anchor, $new_anchor, $url);
        $anchor->attr('href', $new_url);
      }
    }
  }

  /**
   * Removes the host if the url is internal but malformed.
   *
   * A url of 'mysite.com/path1/path2' is malformed because parse_url() will
   * not recognize 'mysite.com' as the host without the scheme (http://) being
   * present.  This method will remove the host if it is for this site and make
   * the url a proper root relative path.
   *
   * @param string $url
   *   A url.
   * @param string $destination_base_url
   *   Destination base URL.
   *
   * @return string
   *   A url or path correctly modified for this site.
   */
  public static function fixSchemelessInternalUrl($url, $destination_base_url) {
    if (!empty($url)) {
      $parsed_url = parse_url($url);
      if (empty($parsed_url['scheme'])) {
        $host = parse_url($destination_base_url, PHP_URL_HOST);
        $pos = stripos($url, $host);
        if ($pos === 0) {
          // The url is starting with a site's host.  Remove it.
          $url = substr_replace($url, '', $pos, strlen($host));
        }
      }
    }
    return $url;
  }

  /**
   * Given a URL or URI return the path and nothing but the path.
   *
   * @param string $href
   *   A URL or URI looking thing.
   *   Ex:
   *   http://www.oldsite.com/section/subsection/index.html
   *   http://www.oldsite.com/section/subsection/
   *   section/subsection/.
   *
   * @return string
   *   The path not containing any filename or extension.
   */
  public static function extractPath($href) {
    // Leading / can confuse parse_url() so get rid of them.
    $href = ltrim($href, '/');
    $path = parse_url($href, PHP_URL_PATH);
    $extension = pathinfo($path, PATHINFO_EXTENSION);
    if ($extension) {
      $path = pathinfo($path, PATHINFO_DIRNAME);
    }
    else {
      $path = pathinfo($path, PATHINFO_DIRNAME) . '/' . pathinfo($path, PATHINFO_BASENAME);
    }

    return $path;
  }

  /**
   * Examines an url to see if it is within a allowed list of domains.
   *
   * @param string $url
   *   A url.
   * @param array $allowed_hosts
   *   A flat array of allowed domains. ex:array('www.site.com', 'site.com').
   * @param string $destination_base_url
   *   Destination base URL.
   *
   * @return bool
   *   TRUE if the host is within the array of allowed.
   *   TRUE if the array of allowed is empty (nothing to compare against)
   *   FALSE if the domain is not with the array of allowed.
   */
  public static function isAllowedDomain($url, array $allowed_hosts, $destination_base_url) {
    $url = self::fixSchemelessInternalUrl($url, $destination_base_url);
    $host = parse_url($url, PHP_URL_HOST);
    // Treat it as allowed until evaluated otherwise.
    $allowed = TRUE;
    if (!empty($allowed_hosts) && (is_array($allowed_hosts)) && (!empty($host))) {
      // See if the host is allowed (case insensitive).
      $allowed = in_array(strtolower($host), array_map('strtolower', $allowed_hosts));
    }
    return $allowed;
  }

  /**
   * Normalize the path to make sure paths are consistent.
   *
   * @param string $uri
   *   A URI. Ex:
   *   'somepath/path/',
   *   'somepath/path'.
   *
   * @return string
   *   The normalized URI. with path ending in / if not a file.
   *   Ex: 'somepath/path/'.
   */
  public static function normalizePathEnding($uri) {
    $uri = trim($uri);
    // If the uri is a path, not ending in a file, make sure it ends in a '/'.
    if (!empty($uri) && !pathinfo($uri, PATHINFO_EXTENSION)) {
      $uri = rtrim($uri, '/');
      $uri .= '/';
    }
    return $uri;
  }

  /**
   * Take parse_url formatted url and return the url/uri as a string.
   *
   * @param array $parsed_url
   *   An array in the format delivered by php php parse_url().
   * @param string $destination_base_url
   *   Destination base URL.
   * @param bool $return_url
   *   Toggles return of full url if TRUE, or uri if FALSE (defaults: TRUE)
   *
   * @return string
   *   URL or URI.
   *
   * @throws \Exception
   */
  public static function reassembleURL(array $parsed_url, $destination_base_url, $return_url = TRUE) {
    $url = '';

    if ($return_url) {
      // It is going to need the scheme and host if there is one.
      $default_scheme = parse_url($destination_base_url, PHP_URL_SCHEME);
      $default_host = parse_url($destination_base_url, PHP_URL_HOST);

      $scheme = (!empty($parsed_url['scheme'])) ? $parsed_url['scheme'] : $default_scheme;
      $scheme = (!empty($scheme)) ? $scheme . '://' : '';

      $host = (!empty($parsed_url['host'])) ? $parsed_url['host'] : $default_host;

      if ((empty($host)) || (empty($scheme))) {
        throw new \Exception("The base domain is needed, but has not been set. Visit /admin/config/migration_tools");
      }
      else {
        // Append / after the host to account for it being removed from path.
        $url .= "{$scheme}{$host}/";
      }
    }

    // Trim the initial '/' to be Drupal friendly in the event of no host.
    $url .= (!empty($parsed_url['path'])) ? ltrim($parsed_url['path'], '/') : '';
    $url .= (!empty($parsed_url['query'])) ? '?' . $parsed_url['query'] : '';
    $url .= (!empty($parsed_url['fragment'])) ? '#' . $parsed_url['fragment'] : '';

    return $url;
  }

  /**
   * Searches for files of the same name and any type .
   *
   * A search for 'xyz.htm' or just 'xyz' will return xyz.htm, xyz.pdf,
   * xyz.html, xyz.doc... if they exist in the directory.
   *
   * @param string $file_name
   *   A filename with or without the extension.
   *   Ex: 'xyz'  or 'xyz.html'.
   * @param string $directory
   *   The directory path relative to the migration source.
   *   Ex: /oldsite/section.
   * @param bool $recurse
   *   Declaring whether to scan recursively into the directory (default: FALSE)
   *   CAUTION: Setting this to TRUE creates the risk of a race condition if
   *   a file with the same name and extension is in multiple locations. The
   *   last one scanned wins.
   *
   * @return array
   *   An array keyed by file extension containing name, filename and uri.
   *   Ex: array (
   *    'pdf' => array(
   *               'name' => 'xyz',
   *               'filename'=> 'xyz.pdf',
   *               'uri'=> '/oldsite/section/xyz.pdf',
   *               'legacy_uri'=> 'migration-source/oldsite/section/xyz.pdf',
   *               'extension'=> 'pdf',
   *             ),
   *   )
   */
  public static function getAllSimilarlyNamedFiles($file_name, $directory, $recurse = FALSE) {
    $processed_files = [];
    if (!empty($file_name)) {
      $file_name = pathinfo($file_name, PATHINFO_FILENAME);
      $regex = '/^' . $file_name . '\..{3,4}$/i';

      // @todo Rework this as $this->baseDir is not available to static methods.
      $migration_source_directory = \Drupal::config('migration_tools.settings')->get('source_directory_base');

      $dir = $migration_source_directory . $directory;
      $options = [
        'key' => 'filename',
        'recurse' => $recurse,
      ];
      $files = \Drupal::service('file_system')->scanDirectory($dir, $regex, $options);
      foreach ($files as $file => $fileinfo) {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $processed_files[$extension] = [
          'name' => $fileinfo->name,
          'filename' => $fileinfo->filename,
          'uri' => $fileinfo->uri,
          'legacy_uri' => str_replace($migration_source_directory . '/', '', $fileinfo->uri),
          'extension' => $extension,
        ];
      }
    }

    return $processed_files;
  }

  /**
   * Check href for  containing an fragment (ex. /blah/index.html#hello).
   *
   * @param string $href
   *   An URL or URI, relative or absolute.
   *
   * @return bool
   *   TRUE - it has a fragment.
   *   FALSE - has no fragment.
   */
  public static function hasFragment($href) {
    if (substr_count($href, "#") > 0) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Check href for only containing a fragment (ex. #hello).
   *
   * @param string $href
   *   An URL or URI, relative or absolute.
   *
   * @return bool
   *   TRUE - it is just a fragment.
   *   FALSE - it is not just a fragment.
   */
  public static function isOnlyFragment($href) {
    $first_char = substr($href, 0, 1);
    if ($first_char === "#") {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Checks if a URL actually resolves to a 'page' on the internet.
   *
   * @param string $url
   *   A full destination URL.
   *   Ex: https://www.oldsite.com/section/blah/index.html.
   * @param bool $follow_redirects
   *   TRUE (default) if you want it to track multiple redirects to the end.
   *   FALSE if you want to only evaluate the first page request.
   *
   * @return mixed
   *   string URL - http response is valid (2xx or 3xx) and has a destination.
   *     Ex: https://www.oldsite.com/section/blah/index.html
   *   bool FALSE - https response is invalid, either 1xx, 4xx, or 5xx
   */
  public static function urlExists($url, $follow_redirects = TRUE) {
    $handle = curl_init();
    curl_setopt($handle, CURLOPT_URL, $url);
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($handle, CURLOPT_FOLLOWLOCATION, $follow_redirects);
    curl_setopt($handle, CURLOPT_HEADER, 0);
    // Get the HTML or whatever is linked in $redirect_url.
    $response = curl_exec($handle);

    // Get status code.
    $http_code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $last_location = curl_getinfo($handle, CURLINFO_EFFECTIVE_URL);

    $url = ($follow_redirects) ? $last_location : $url;

    // Check that http code exists.
    if ($http_code) {
      // Determines first digit of http code.
      $first_digit = substr($http_code, 0, 1);
      // Filters for 2 or 3 as first digit.
      if ($first_digit == 2 || $first_digit == 3) {
        return $url;
      }
      else {
        // Invalid url.
        return FALSE;
      }
    }
  }

  /**
   * Pull a URL destination from a Javascript script.
   *
   * @param string $string
   *   String of the script contents.
   *
   * @return mixed
   *   string - the validated URL if found.
   *   bool - FALSE if no valid URL was found.
   */
  public static function extractUrlFromJS($string) {
    // Look for imposters.
    $imposters = [
      'location.protocol',
      'location.host',
    ];
    foreach ($imposters as $imposter) {
      $is_imposter = stripos($string, $imposter);
      if ($is_imposter !== FALSE) {
        // It is an imposter, so bail.
        return FALSE;
      }
    }
    // Array of items to search for.
    $searches = [
      'location.replace',
      'location.href',
      'location.assign',
      'location.replace',
      "'location'",
      'location',
      "'href'",
    ];

    // Array of starts and ends to try locating.
    $wrappers = [];
    // Provide two elements: the beginning and end wrappers.
    $wrappers[] = ['"', '"'];
    $wrappers[] = ["'", "'"];

    foreach ($searches as $search) {
      foreach ($wrappers as $wrapper) {
        $url = self::peelUrl($string, $search, $wrapper[0], $wrapper[1]);
        if (!empty($url)) {
          return $url;
        }
      }
    }
    return FALSE;
  }

  /**
   * Searches $haystack for a prelude string then returns the next url found.
   *
   * @param string $haystack
   *   The html string to search through.
   * @param string $prelude_string
   *   The text that appears before the url for a redirect.
   * @param string $wrapper_start
   *   The first part that the url is wrapped in: " ' [ (.
   * @param string $wrapper_end
   *   The last part that the url is wrapped in: " ' } ).
   *
   * @return mixed
   *   string - The valid URL found.
   *   bool - FALSE if no valid URL is found.
   */
  public static function peelUrl($haystack, $prelude_string, $wrapper_start, $wrapper_end) {
    $wrapped = preg_split("/{$prelude_string}/i", $haystack, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
    // If something was found there will be > 1 element in the array.
    if (count($wrapped) > 1) {
      $found = $wrapped[1];
      $start_location = stripos($found, $wrapper_start);
      // Lets set a limit to how far this will search from the $prelude_string.
      // Anything more than 75 characters ahead is risky.
      $start_location = ($start_location < 75) ? $start_location : FALSE;
      // Account for the length of the start wrapper.
      $start_location = ($start_location !== FALSE) ? $start_location + strlen($wrapper_start) : FALSE;
      // Offset the search for the end, so the start does not get found x2.
      $end_location = ($start_location !== FALSE) ? stripos($found, $wrapper_end, $start_location) : FALSE;
      // Need both a start and end to grab the middle.
      if (($start_location !== FALSE) && ($end_location !== FALSE) && ($end_location > $start_location)) {
        $url = substr($found, $start_location, $end_location - $start_location);
        $url = StringTools::superTrim($url);
        // Make sure we have a valid URL.
        if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
          return $url;
        }
      }
    }
    return FALSE;
  }

  /**
   * Alter image src in page that are relative, absolute or full alter base.
   *
   * Relative src will be made either absolute or root relative depending on
   * the value of $base_for_relative.  If root relative is used, then attempts
   * will be made to lookup the redirect and detect the final destination.
   *
   * @param object $query_path
   *   A query path object containing the page html.
   * @param array $url_base_alters
   *   An array of url bases to alter in the form of old-link => new-link
   *   array(
   *     'www.oldsite.com/section' => 'https://www.newsite.com/new-section',
   *     'www.oldsite.com/section' => 'https://www.newsite.com/new-section',
   *     'www.oldsite.com/' => 'https://www.newsite.com',
   *     'https://www.oldsite.com/' => 'https://www.newsite.com',
   *     'https:/subdomain.oldsite.com' => 'https://www.othersite.com/secure',
   *     'http:/subdomain.oldsite.com' => 'https://www.othersite.com/public',
   *   )
   *   NOTE: Order matters.  First one to match, wins.
   * @param string $file_path
   *   A file path for the location of the source file.
   *   Ex: /oldsite/section/blah/index.html.
   * @param string $base_for_relative
   *   The base directory or host+base directory to prepend to relative hrefs.
   *   Ex: https://www.oldsite.com/section  - if it needs to point to the source
   *   server.
   *   redirect-oldsite/section - if the links should be made internal.
   * @param string $destination_base_url
   *   Destination base URL.
   */
  public static function rewriteImageHrefsOnPage($query_path, array $url_base_alters, $file_path, $base_for_relative, $destination_base_url) {
    // Find all the images on the page.
    $image_srcs = $query_path->top('img[src]');
    // Initialize summary report information.
    $image_count = $image_srcs->size();
    $report = [];
    // Loop through them all looking for src to alter.
    foreach ($image_srcs as $image) {
      $href = trim($image->attr('src'));
      $new_href = self::rewritePageHref($href, $url_base_alters, $file_path, $base_for_relative, $destination_base_url);
      // Set the new href.
      $image->attr('src', $new_href);

      if ($href !== $new_href) {
        // Something was changed so add it to report.
        $report[] = "$href changed to $new_href";
      }
    }
    // Message the report (no log).
    Message::makeSummary($report, $image_count, 'Rewrote img src');
  }

  /**
   * Alter image that are relative (have no scheme or host) to media tokens.
   *
   * @param object $query_path
   *   A query path object containing the page html.
   * @param array $entity_lookup
   *   Contains details about what to lookup and find the image.
   * @param array $media_parameters
   *   The parameters to be used by default in the construction of image media.
   *   $media_parameters = [
   *     'alt' => '',
   *    'title' => '',
   *     'align' => '',
   *     'caption' => '',
   *     'embed_button' => 'media_browser',
   *     'embed_display' => 'media_image',
   *     'embed_display_settings' => '',
   *     'entity_type' => 'media',
   *      'entity_uuid' => '',
   *   ];.
   */
  public static function rewriteRelativeImageHrefsToMedia($query_path, array $entity_lookup, array $media_parameters) {
    // Find all the images on the page.
    $images = $query_path->top('img[src]');
    // Initialize summary report information.
    $image_count = $images->size();
    $report = [];
    // Loop through them all looking for src to alter.
    foreach ($images as $image) {
      if (self::isImageMap($image)) {
        // This is an image map and should not be converted to a media embed.
        // Skip and move on to the next.
        continue;
      }

      $href = trim($image->attr('src'));
      // Check to see if it is relative.
      if (self::isRelativeUrl($href)) {
        // This url may have a media element. Let's look.
        if ($entity_lookup['method'] === 'migrate_map') {
          $media_id = self::lookupMigrateDestinationIdByKeyPath($href, $entity_lookup['migrations'], $entity_lookup['ignore_path']);
        }

        // If we have nothing, or was the requested method, look for redirect.
        if ((empty($media_id)) && ($entity_lookup['method'] === 'redirect')) {
          $media_id = Media::lookupMediaByRedirect($href);
        }

        if (empty($media_id)) {
          // Found nothing to create a media token from.  Do nothing and next.
          continue;
        }

        $media_parameters['entity_uuid'] = Media::getMediaUuidfromMid($media_id);

        // Let's grab any attributes.
        $alt = trim($image->attr('alt'));
        if (!empty($alt)) {
          $media_parameters['alt'] = $alt;
        }
        $title = trim($image->attr('title'));
        if (!empty($title)) {
          $media_parameters['title'] = $title;
        }
        $align = trim($image->attr('align'));

        if (!empty($align) && empty($media_parameters['align'])) {
          $media_parameters['align'] = $align;
        }

        // Grab caption from <figcaption> if it's there.
        $caption = $image->next('figcaption')->innerHtml();
        if (!empty($caption)) {
          $media_parameters['caption'] = $caption;
        }
      }
      $media_parameters['embed_display'] = 'entity_reference:media_full';

      $media_embed = Media::buildMediaEmbed($media_parameters);
      if (!empty($media_embed)) {
        // There is a media entity, so lets use it.
        $image->after($media_embed);
        $image->remove();
        // Something was changed so add it to report.
        $report[] = "{$href} changed to media {$media_parameters['entity_uuid']}";
      }
    }
    // Message the report (no log).
    Message::makeSummary($report, $image_count, 'Rewrote img src to Media.');
  }

  /**
   * Lookup a migrate destination ID by path.
   *
   * WARNING This only works if the key in the migration is the uri of the file.
   *
   * @param string $source_key_uri
   *   The root relative uri for the media item on the legacy site.
   * @param array $migrations
   *   An array list of migration id (machine_names) to try searching within.
   * @param string $trim_path
   *   Any redirect namespace to remove from the uri prior to the search.
   *
   * @return int
   *   The destination media id. if media was found. Empty string if none found.
   */
  public static function lookupMigrateDestinationIdByKeyPath(string $source_key_uri, array $migrations, string $trim_path = '') {
    $found_id = '';
    if (!empty($source_key_uri)) {
      // Go search.
      self::trimPath($source_key_uri, $trim_path);
      foreach ($migrations as $migration) {
        $map_table = "migrate_map_{$migration}";
        $database = \Drupal::database();
        $result = $database->select($map_table, 'm')
          ->condition('m.sourceid1', "%" . $database->escapeLike($source_key_uri), 'LIKE')
          ->fields('m', ['destid1'])
          ->execute()
          ->fetchAll();

        // Should only have one result.
        if (count($result) === 1) {
          // We got 1, call off the search.
          $mapkey = reset($result);
          $found_id = $mapkey->destid1;
          break;
        }
      }
    }

    return $found_id;
  }

  /**
   * Removes a trimpath (redirect namespace) from a url by reference.
   *
   * @param string $source_key_uri
   *   A relative uri to be trimmed.
   * @param string $trim_path
   *   A path to be removed from the $source_key_uri.
   */
  public static function trimPath(&$source_key_uri, $trim_path) {
    // Remove any / from the ends so we can re-apply them with more certainty.
    $trim_path = trim($trim_path, '/');
    $source_key_uri = str_replace("/{$trim_path}/", '/', $source_key_uri);
  }

  /**
   * Alter hrefs in page if they point to non-html-page files.
   *
   * Relative src will be made either absolute or root relative depending on
   * the value of $base_for_relative.  If root relative is used, then attempts
   * will be made to lookup the redirect and detect the final destination.
   *
   * @param object $query_path
   *   A query path object containing the page html.
   * @param array $url_base_alters
   *   An array of url bases to alter in the form of old-link => new-link
   *   array(
   *     'www.oldsite.com/section' => 'https://www.newsite.com/new-section',
   *     'www.oldsite.com/section' => 'https://www.newsite.com/new-section',
   *     'www.oldsite.com/' => 'https://www.newsite.com',
   *     'https://www.oldsite.com/' => 'https://www.newsite.com',
   *     'https:/subdomain.oldsite.com' => 'https://www.othersite.com/secure',
   *     'http:/subdomain.oldsite.com' => 'https://www.othersite.com/public',
   *   )
   *   NOTE: Order matters.  First one to match, wins.
   * @param string $file_path
   *   A file path for the location of the source file.
   *   Ex: /oldsite/section/blah/index.html.
   * @param string $base_for_relative
   *   The base directory or host+base directory to prepend to relative hrefs.
   *   Ex: https://www.oldsite.com/section  - if it needs to point to the source
   *   server.
   *   redirect-oldsite/section - if the links should be made internal.
   * @param string $destination_base_url
   *   Destination base URL.
   */
  public static function rewriteAnchorHrefsToBinaryFiles($query_path, array $url_base_alters, $file_path, $base_for_relative, $destination_base_url) {
    $attributes = [
      'href' => 'a[href], area[href]',
      'longdesc' => 'img[longdesc]',
    ];
    $filelink_count = 0;
    $report = [];
    foreach ($attributes as $attribute => $selector) {
      // Find all the $selector on the page.
      $binary_file_links = $query_path->top($selector);
      $filelink_count += $binary_file_links->size();
      // Loop through them all looking for href to alter.
      foreach ($binary_file_links as $link) {
        $href = trim($link->attr($attribute));
        if (CheckFor::isFile($href)) {
          $new_href = self::rewritePageHref($href, $url_base_alters, $file_path, $base_for_relative, $destination_base_url);
          // Set the new href.
          $link->attr($attribute, $new_href);

          if ($href !== $new_href) {
            // Something was changed so add it to report.
            $report[] = "$attribute: $href changed to $new_href";
          }
        }
      }
    }
    // Message the report (no log).
    Message::makeSummary($report, $filelink_count, 'Rewrote binary file hrefs');
  }

  /**
   * Alter relative script source paths in page if they point to js and swf.
   *
   * Relative src will be made either absolute or root relative depending on
   * the value of $base_for_relative.  If root relative is used, then attempts
   * will be made to lookup the redirect and detect the final destination.
   *
   * @param object $query_path
   *   A query path object containing the page html.
   * @param array $url_base_alters
   *   An array of url bases to alter in the form of old-link => new-link
   *   array(
   *     'www.oldsite.com/section' => 'https://www.newsite.com/new-section',
   *     'www.oldsite.com/section' => 'https://www.newsite.com/new-section',
   *     'www.oldsite.com/' => 'https://www.newsite.com',
   *     'https://www.oldsite.com/' => 'https://www.newsite.com',
   *     'https:/subdomain.oldsite.com' => 'https://www.othersite.com/secure',
   *     'http:/subdomain.oldsite.com' => 'https://www.othersite.com/public',
   *   )
   *   NOTE: Order matters.  First one to match, wins.
   * @param string $file_path
   *   A file path for the location of the source file.
   *   Ex: /oldsite/section/blah/index.html.
   * @param string $base_for_relative
   *   The base directory or host+base directory to prepend to relative hrefs.
   *   Ex: https://www.oldsite.com/section  - if it needs to point to the source
   *   server.
   *   redirect-oldsite/section - if the links should be made internal.
   * @param string $destination_base_url
   *   Destination base URL.
   */
  public static function rewriteScriptSourcePaths($query_path, array $url_base_alters, $file_path, $base_for_relative, $destination_base_url) {
    $attributes = [
      'src' => 'script[src], embed[src]',
      'value' => 'param[value]',
    ];
    $script_path_count = 0;
    $report = [];
    self::rewriteFlashSourcePaths($query_path, $url_base_alters, $file_path, $base_for_relative, $destination_base_url);
    foreach ($attributes as $attribute => $selector) {
      // Find all the selector on the page.
      $links_to_pages = $query_path->top($selector);
      // Initialize summary report information.
      $script_path_count += $links_to_pages->size();
      // Loop through them all looking for src or value path to alter.
      foreach ($links_to_pages as $link) {
        $href = trim($link->attr($attribute));
        $new_href = self::rewritePageHref($href, $url_base_alters, $file_path, $base_for_relative, $destination_base_url);
        // Set the new href.
        $link->attr($attribute, $new_href);

        if ($href !== $new_href) {
          // Something was changed so add it to report.
          $report[] = "$attribute: $href changed to $new_href";
        }
      }
    }
    // Message the report (no log).
    Message::makeSummary($report, $script_path_count, 'Rewrote script src');
  }

  /**
   * Alter relative Flash source paths in page scripts.
   *
   * Relative src will be made either absolute or root relative depending on
   * the value of $base_for_relative.  If root relative is used, then attempts
   * will be made to lookup the redirect and detect the final destination.
   *
   * @param object $query_path
   *   A query path object containing the page html.
   * @param array $url_base_alters
   *   An array of url bases to alter in the form of old-link => new-link
   *   array(
   *     'www.oldsite.com/section' => 'https://www.newsite.com/new-section',
   *     'www.oldsite.com/section' => 'https://www.newsite.com/new-section',
   *     'www.oldsite.com/' => 'https://www.newsite.com',
   *     'https://www.oldsite.com/' => 'https://www.newsite.com',
   *     'https:/subdomain.oldsite.com' => 'https://www.othersite.com/secure',
   *     'http:/subdomain.oldsite.com' => 'https://www.othersite.com/public',
   *   )
   *   NOTE: Order matters.  First one to match, wins.
   * @param string $file_path
   *   A file path for the location of the source file.
   *   Ex: /oldsite/section/blah/index.html.
   * @param string $base_for_relative
   *   The base directory or host+base directory to prepend to relative hrefs.
   *   Ex: https://www.oldsite.com/section  - if it needs to point to the source
   *   server.
   *   redirect-oldsite/section - if the links should be made internal.
   * @param string $destination_base_url
   *   Destination base URL.
   */
  public static function rewriteFlashSourcePaths($query_path, array $url_base_alters, $file_path, $base_for_relative, $destination_base_url) {
    $scripts = $query_path->top('script[type="text/javascript"]');
    foreach ($scripts as $script) {
      $needles = [
        "'src','",
        "'movie','",
      ];
      $script_content = $script->text();
      foreach ($needles as $needle) {
        $start_loc = stripos($script_content, $needle);
        if ($start_loc !== FALSE) {
          $length_needle = strlen($needle);
          // Shift to the end of the needle.
          $start_loc = $start_loc + $length_needle;
          $end_loc = stripos($script_content, "'", $start_loc);
          $target_length = $end_loc - $start_loc;
          $old_path = substr($script_content, $start_loc, $target_length);
          if (!empty($old_path)) {
            // Process the path.
            $new_path = self::rewritePageHref($old_path, $url_base_alters, $file_path, $base_for_relative, $destination_base_url);
            // Replace.
            $script_content = str_replace("'$old_path'", "'$new_path'", $script_content);
            if ($old_path !== $new_path) {
              // The path changed, so put it back.
              $script->text($script_content);
            }
          }
        }
      }
    }
  }

  /**
   * Alter hrefs in page if they point to html-page files.
   *
   * Relative src will be made either absolute or root relative depending on
   * the value of $base_for_relative.  If root relative is used, then attempts
   * will be made to lookup the redirect and detect the final destination.
   *
   * @param object $query_path
   *   A query path object containing the page html.
   * @param array $url_base_alters
   *   An array of url bases to alter in the form of old-link => new-link
   *   array(
   *     'www.oldsite.com/section' => 'https://www.newsite.com/new-section',
   *     'www.oldsite.com/section' => 'https://www.newsite.com/new-section',
   *     'www.oldsite.com/' => 'https://www.newsite.com',
   *     'https://www.oldsite.com/' => 'https://www.newsite.com',
   *     'https:/subdomain.oldsite.com' => 'https://www.othersite.com/secure',
   *     'http:/subdomain.oldsite.com' => 'https://www.othersite.com/public',
   *   )
   *   NOTE: Order matters.  First one to match, wins.
   * @param string $file_path
   *   A file path for the location of the source file.
   *   Ex: /oldsite/section/blah/index.html.
   * @param string $base_for_relative
   *   The base directory or host+base directory to prepend to relative hrefs.
   *   Ex: https://www.oldsite.com/section  - if it needs to point to the source
   *   server.
   *   redirect-oldsite/section - if the links should be made internal.
   * @param string $destination_base_url
   *   Destination base URL.
   */
  public static function rewriteAnchorHrefsToPages($query_path, array $url_base_alters, $file_path, $base_for_relative, $destination_base_url) {
    $attributes = [
      'href' => 'a[href], area[href]',
      'longdesc' => 'img[longdesc]',
    ];
    $pagelink_count = 0;
    $report = [];
    foreach ($attributes as $attribute => $selector) {
      // Find all the hrefs on the page.
      $links_to_pages = $query_path->top($selector);
      // Initialize summary report information.
      $pagelink_count += $links_to_pages->size();
      // Loop through them all looking for href to alter.
      foreach ($links_to_pages as $link) {
        $href = trim($link->attr('href'));
        if (CheckFor::isPage($href)) {
          $new_href = self::rewritePageHref($href, $url_base_alters, $file_path, $base_for_relative, $destination_base_url);
          // Set the new href.
          $link->attr($attribute, $new_href);

          if ($href !== $new_href) {
            // Something was changed so add it to report.
            Message::make("$attribute: $href changed to $new_href", [], FALSE);
            $report[] = "$attribute: $href changed to $new_href";
          }
        }
      }
    }
    // Message the report (no log).
    Message::makeSummary($report, $pagelink_count, 'Rewrote page hrefs');
  }

  /**
   * Alter URIs and URLs in page that are relative, absolute or full alter base.
   *
   * Relative links will be made either absolute or root relative depending on
   * the value of $base_for_relative.  If root relative is used, then attempts
   * will be made to lookup the redirect and detect the final destination.
   *
   * @param string $href
   *   The href from a link, img src  or img long description.
   * @param array $url_base_alters
   *   An array of url bases to alter in the form of old-link => new-link
   *   Examples:
   *   array(
   *     'http://www.old.com/section' => 'https://www.new.com/new-section',
   *     'https://www.old.com/section' => 'https://www.new.com/new-section',
   *     'https://www.old.com/section' => '/redirect-old/new-section',
   *     'www.old.com/' => 'www.new.com',
   *     'https://www.old.com/' => 'https://www.new.com',
   *     'https:/subdomain.old.com' => 'https://www.other.com/secure',
   *     'http:/subdomain.old.com' => 'https://www.other.com/public',
   *   )
   *   NOTE: Order matters.  First one to match, wins.
   * @param string $file_path
   *   A file path for the location of the source file.
   *   Ex: /oldsite/section/blah/index.html.
   * @param string $base_for_relative
   *   The base directory or host+base directory to prepend to relative hrefs.
   *   Ex: https://www.oldsite.com/section  - if it needs to point to the source
   *   server.
   *   redirect-oldsite/section - if the links should be made internal.
   * @param string $destination_base_url
   *   Destination base URL.
   *
   * @return string
   *   The processed href.  Local urls will be converted to root relative.
   */
  public static function rewritePageHref($href, array $url_base_alters, $file_path, $base_for_relative, $destination_base_url) {
    if (!empty($href)) {
      // Fix relatives Using the $base_for_relative and file_path.
      $source_file = $base_for_relative . '/' . $file_path;
      $href = self::convertRelativeToAbsoluteUrl($href, $source_file, $destination_base_url);

      // If the href matches a $url_base_alters  swap them.
      foreach ($url_base_alters as $old_base => $new_base) {
        if (stripos($href, $old_base) !== FALSE) {
          $href = str_ireplace($old_base, $new_base, $href);
          // The first replacement won, so call it quits on the replacement.
          break;
        }
      }
    }

    // For local links, make them root relative.
    $href_host = parse_url($href, PHP_URL_HOST);
    $href_scheme = parse_url($href, PHP_URL_HOST);
    $destination_host = parse_url($destination_base_url, PHP_URL_HOST);
    if (!empty($href_scheme) && !empty($href_host) && ($destination_host == $href_host)) {
      // This is a local url so should have the scheme and host removed.
      $href_parsed = parse_url($href);
      $href = self::reassembleURL($href_parsed, $destination_base_url, FALSE);
      $href = '/' . ltrim($href, '/');
    }
    return $href;
  }

  /**
   * Return the url query string as an associative array.
   *
   * @param string $query
   *   The string from the query parameters of an URL.
   *
   * @return array
   *   The query parameters as an associative array.
   */
  public static function convertUrlQueryToArray($query) {
    $query_parts = explode('&', $query);
    $params = [];
    foreach ($query_parts as $param) {
      $item = explode('=', $param);
      $params[$item[0]] = $item[1];
    }

    return $params;
  }

  /**
   * Outputs the sorted contents of ->pathing to terminal for inspection.
   *
   * @param string $message
   *   (optional) A message to prepend to the output.
   */
  public function debug($message = '') {
    // Sort it by property name for ease use.
    $properties = get_object_vars($this);
    ksort($properties);
    $sorted_object = new \stdClass();
    // Rebuild a new sorted object for output.
    foreach ($properties as $property_name => $property) {
      $sorted_object->$property_name = $property;
    }

    Message::varDumpToDrush($sorted_object, "$message Contents of pathing: ");
  }

}
