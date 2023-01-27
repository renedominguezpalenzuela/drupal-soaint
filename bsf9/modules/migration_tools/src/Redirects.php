<?php

namespace Drupal\migration_tools;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url as DrupalUrl;
use Drupal\file\Entity\File;
use Drupal\Media\Entity\Media;
use Drupal\Media\MediaInterface;
use Drupal\migrate\MigrateException;
use Drupal\migration_tools\Url;
use Drupal\redirect\Entity\Redirect;
use Drupal\redirect\RedirectRepository;

class Redirects {

  /**
   * The migration tools settings array as defined by the migration yml file.
   *
   * @var array
   */
  public $migrationToolsSettings;


  /**
   * A correctly namespaced source path for the item being redirected.
   *
   * @var string
   */
  protected $namespacedUri;

  /**
   * An array of url sources to create redirects from.
   *
   * There is a potential for there to be several source urls that end up
   * redirecting to one destination.
   *
   * @var array
   *   A list of redirect source urls.
   */
  public $redirectSources;


  /**
   * The path of the source.
   *
   * @var string
   */
  protected $sourcePath;

  /**
   * Drupal migrate row object.
   *
   * @var object
   */
  protected $row;

  /**
   * Constructor for class.
   *
   * @param object $row
   *   The migrate row object.
   */
  public function __construct(&$row) {
    $migration_tools_settings = $row->getSourceProperty('migration_tools');
    // See if redirects should be processed.
    if (!empty($migration_tools_settings)) {
      // @TODO rework assumption that there is only one migration tools array.
      $this->migrationToolsSettings = $migration_tools_settings[0];
      $this->row = $row;
      $this->backfillDefaults();
      if ($this->getRedirectSetting('create')) {
        // We should create redirects, so begin construction.
        $this->redirectSources = [];
        // We are supposed to create a redirect, lets see if we have an url.
        if ($this->row->hasSourceProperty('source_url') && !empty($this->row->getSourceProperty('source_url'))) {
          // An array of incompatible strings in redirect source URLs.
          $strings_to_replace = [
            "%20" => " ",
          ];
          // Replace bad stings in URL.
          $sourceUrl = str_replace(array_keys($strings_to_replace), array_values($strings_to_replace), $this->row->getSourceProperty('source_url'));
          // One is defined directly in the row so use that.
          $this->addRedirectSource($sourceUrl);
        }
        elseif ($this->getMigrationToolsSetting('source_type') == 'url' && !empty($this->getMigrationToolsSetting('source'))) {
          // Might be able to infer one from the source location.
          $this->addRedirectSource($this->getMigrationToolsSetting('source'));
          $sourceUrl = $this->getMigrationToolsSetting('source');
        }

        if (!empty($sourceUrl)) {
          $this->addRedirectIfIndex($sourceUrl, $this->getRedirectSetting('base_url'));
        }

        // @TODO restore processing for sensing redirects using
        // hasValidRedirect().
      }
    }
  }

  /**
   * Adds a unique redirect source url to the $redirect_sources array.
   *
   * @param string $source
   *   The source path to create a redirect.
   */
  public function addRedirectSource(string $source) {
    if (!empty($source) && !in_array($source, $this->redirectSources, TRUE)) {
      // The source has not been added yet, so add it.
      $this->redirectSources[] = $source;
    }
  }

  /**
   * Getter for Redirect Settings.
   *
   * @param string $propertyName
   *   The name of the redirect setting property to return.
   *
   * @return mixed
   *   string || array: depending on what is in the property.
   *   array: defaults to empty array if no item exists for that property.
   */
  protected function getRedirectSetting($propertyName) {
    return (!empty($this->migrationToolsSettings['redirect'][$propertyName])) ? $this->migrationToolsSettings['redirect'][$propertyName] : [];
  }

  /**
   * Getter for Migration Tools Settings.
   *
   * @param string $propertyName
   *   The name of the migration tool setting property to return.
   *
   * @return mixed
   *   string || array: depending on what is in the property.
   *   array: defaults to empty array if no item exists for that property.
   */
  protected function getMigrationToolsSetting($propertyName) {
    return (!empty($this->migrationToolsSettings[$propertyName])) ? $this->migrationToolsSettings[$propertyName] : [];
  }

  /**
   * Sets and default values on the redirect settings.
   *
   * This also accounts for migrations that used a deprecated setting pattern.
   */
  protected function backfillDefaults() {
    if (!isset($this->migrationToolsSettings['redirect'])) {
      // Uses older yml format. Convert them to avoid breaking older version.
      $redirect = [
        'create' => !empty($this->getMigrationToolsSetting['create_redirects']) ? $this->getMigrationToolsSetting['create_redirects'] : FALSE,
        'preserve_query_params' => !empty($this->getMigrationToolsSetting['redirect_preserve_query_params']) ? $this->getMigrationToolsSetting['redirect_preserve_query_params'] : FALSE,
        'source_namespace' => !empty($this->getMigrationToolsSetting['redirect_source_namespace']) ? trim($this->getMigrationToolsSetting['redirect_source_namespace'], '/') : '',
      ];
      $this->migrationToolsSettings['redirect'] = $redirect;
    }
    // Backfill defaults.
    $redirect_defaults = [
      'create' => FALSE,
      'preserve_query_params' => FALSE,
      'source_namespace' => '',
      'language' => \Drupal::languageManager()->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId(),
      'index_filenames' => [],
      'scan_for' => [
        'server_side_redirects' => FALSE,
        'header_redirects'  => FALSE,
        'js_redirects' => FALSE,
        'fake_redirects' => FALSE,
      ],
    ];

    $this->migrationToolsSettings['redirect'] = array_merge($redirect_defaults, $this->migrationToolsSettings['redirect']);

    // Clean-up entries.
    $this->migrationToolsSettings['redirect']['source_namespace'] = (!empty($this->getRedirectSetting('source_namespace'))) ? trim($this->getRedirectSetting('source_namespace'), '/') . '/' : '';
    // Save for use elsewhere.
    $this->row->setSourceProperty('migration_tools', $this->migrationToolsSettings);
  }

  /**
   * Grabs legacy redirects for this node from D6 and adds $row->redirects.
   *
   * This function needs to be called in prepareRow() of your migration.
   *
   * @param object $row
   *   The object of this row.
   * @param string $db_reference_name
   *   The Drupal name/identifier of the legacy database.
   * @param object $source_connection
   *   Database source connection from migration.
   */
  public static function collectD6RedirectsToThisNode($row, $db_reference_name, $source_connection) {
    // @todo D8 Refactor  -This probably no longer needed as redirects from
    // drupal to drupal have their own migration.
    // Gather existing redirects from legacy.
    $row->redirects = \Database::getConnection($db_reference_name, $source_connection)
      ->select('path_redirect', 'r')
      ->fields('r', ['source'])
      ->condition('redirect', "node/$row->nid")
      ->execute()
      ->fetchCol();
  }

  /**
   * Generates a drupal-centric URI based in the redirect namespace.
   *
   * @param string $pathing_legacy_directory
   *   (optional) The directory housing the migration source.  Only needed if
   *   one section of one site is being moved to another site.
   *   ex: If var/www/migration-source/oldsite, then 'oldsite' is the directory.
   * @param string $pathing_redirect_namespace
   *   (optional) The fake directory used for namespacing the redirects.
   *   ex: 'redirect-oldsite'.
   *
   * @var string $this->namespacedUri
   *   Created property.
   *   ex: redirect-oldsite/section/blah/index.html
   */
  public function generateNamespacedUri($pathing_legacy_directory = '', $pathing_redirect_namespace = '') {
    // Allow the parameters to override the property if provided.
    $pathing_legacy_directory = (!empty($pathing_legacy_directory)) ? $pathing_legacy_directory : $this->getRedirectSetting('source_legacy_directory');
    $pathing_redirect_namespace = (!empty($pathing_redirect_namespace)) ? $pathing_redirect_namespace : $this->getRedirectSetting('source_namespace');
    $uri = ltrim($this->sourcePath, '/');
    if (!empty($pathing_legacy_directory) && !empty($pathing_redirect_namespace)) {
      // Swap the pathing_legacy_directory for the pathing_redirect_namespace.
      $this->namespacedUri = str_replace($pathing_legacy_directory, $pathing_redirect_namespace, $uri);
    }
    elseif (!empty($pathing_redirect_namespace)) {
      // No legacy adjustments needed, just namespace it.
      $this->namespacedUri = "{$pathing_redirect_namespace}{$uri}";
    }
    else {
      $this->namespacedUri = $uri;
    }
  }

  /**
   * Creates a redirect from a legacy path if one does not exist.
   *
   * @param string $source_path
   *   The path or url of the legacy source. MUST be INTERNAL to this site.
   *   Ex: redirect-oldsite/section/blah/index.html,
   *   https://www.this-site.com/somepage.htm
   *   http://external-site.com/somepate.htm [only if external-site.com is in
   *   the allowed hosts array].
   * @param string $destination
   *   The destination of the redirect Ex:
   *   node/123
   *   swapped-section-a/blah/title-based-thing
   *   http://www.some-other-site.com.
   * @param string $destination_base_url
   *   Destination base URL.
   * @param array $allowed_hosts
   *   If passed, this will limit redirect creation to only urls that have a
   *   domain present in the array. Others will be rejected.
   *
   * @return bool
   *   FALSE if error.
   */
  public function createRedirect($source_path, $destination, $destination_base_url, array $allowed_hosts = []) {
    $alias = $destination;

    // We can not create a redirect for a URL that is not part of the domain
    // or subdomain of this site.
    if (!Url::isAllowedDomain($source_path, $allowed_hosts, $destination_base_url)) {
      $message = "A redirect was NOT built for @source_path because it is not an allowed host.";
      $variables = [
        '@source_path' => $source_path,
      ];
      Message::make($message, $variables, FALSE, 2);
      return FALSE;
    }

    if (!empty($source_path)) {
      // Alter source path to remove any externals.
      $source_path = Url::fixSchemelessInternalUrl($source_path, $destination_base_url);
      $source = parse_url($source_path);
      $source_path = (!empty($source['path'])) ? $source['path'] : '';
      // A path should not have a preceding /.
      $this->sourcePath = ltrim($source['path'], '/');
      // Namespace this source path.
      $this->generateNamespacedUri();
      $source_path = $this->namespacedUri;

      $source_options = [];
      // Check for fragments (after #hash ).
      if (!empty($source['fragment'])) {
        $source_options['fragment'] = $source['fragment'];
      }

      // Check for query parameters (after ?).
      if (!empty($source['query'])) {
        parse_str($source['query'], $query);
        $source_options['query'] = $query;
      }
      else {
        $source_options['query'] = [];
      }

      // Check to see if the source and destination or alias are the same.
      if (($source_path !== $destination) && ($source_path !== $alias)) {
        // The source and destination are different, so make the redirect.
        $matched_redirect = $this->row->redirectRepository->findMatchingRedirect($source_path, $source_options['query'], $this->getRedirectSetting('language'));

        if (is_null($matched_redirect)) {
          // The redirect does not exists so create it.
          $redirect_storage = $this->row->entityTypeManager->getStorage('redirect');
          $redirect = $redirect_storage->create();
          $redirect->setSource($source_path, $source_options['query']);
          // Should query params be present in the destination?
          if (!empty($this->getRedirectSetting('preserve_query_params'))) {
            // Query params should be preserved in the destination.
            $redirect->setRedirect($destination, $source_options['query']);
          }
          else {
            // Query params should NOT be used in the destination.
            $redirect->setRedirect($destination);
          }
          $redirect->setLanguage($this->getRedirectSetting('language'));
          $redirect->setStatusCode(301);
          $redirect->save();

          $message = 'Redirect created: @source ---> @destination';
          $variables = [
            '@source' => $source_path,
            '@destination' => $destination,
          ];
          Message::make($message, $variables, FALSE, 1);
        }
        else {
          // The redirect already exists.
          $message = 'The redirect of @legacy already exists pointing to @alias. A new one was not created.';
          $variables = [
            '@legacy' => $source_path,
            '@alias' => $destination,
          ];
          Message::make($message, $variables, FALSE, 1);
        }
      }
      else {
        // The source and destination are the same. So no redirect needed.
        $message = 'The redirect of @source have identical source and destination. No redirect created.';
        $variables = [
          '@source' => $source_path,
        ];
        Message::make($message, $variables, FALSE, 1);
      }
    }
    else {
      // The is no value for redirect.
      $message = 'The source path is missing. No redirect can be built.';
      $variables = [];
      Message::make($message, $variables, FALSE, 1);
    }
    return TRUE;
  }

  /**
   * Creates multiple redirects to the same destination.
   *
   * This is typically called within the migration's complete().
   *
   * @param array $redirects
   *   The paths or URIs of the legacy source. MUST be INTERNAL to this site.
   *   Ex: redirect-oldsite/section/blah/index.html,
   *   https://www.this-site.com/somepage.htm
   *   http://external-site.com/somepate.htm [only if external-site.com is in
   *   the allowed hosts array].
   * @param string $destination
   *   The destination of the redirect Ex:
   *   node/123
   *   swapped-section-a/blah/title-based-thing
   *   http://www.some-other-site.com.
   * @param string $destination_base_url
   *   Destination base URL.
   * @param array $allowed_hosts
   *   If passed, this will limit redirect creation to only urls that have a
   *   domain present in the array. Others will be rejected.
   */
  public function createRedirectsMultiple(array $redirects, $destination, $destination_base_url, array $allowed_hosts = []) {
    foreach ($redirects as $redirect) {
      if (!empty($redirect)) {
        $this->createRedirect($redirect, $destination, $destination_base_url, $allowed_hosts);
      }
    }
  }

  /**
   * Triggers the saving of all accumulated redirects the entity.
   *
   * Should be called in PostRowSave or we need an entity id.
   *
   * @param int $entityID
   *   The Drupal entity id assign as the redirect destination.
   */
  public function saveRedirects(int $entityID) {
    if (!empty($entityID)) {
      $destination = $this->createDestination($entityID);
      $destination_base_url = $this->getRedirectSetting('base_url');
      $allowed_domains = $this->getRedirectSetting('allowed_domains');
      // Make sure we have the essentials.
      if (!empty($destination) && !empty($destination_base_url)) {
        $this->createRedirectsMultiple($this->redirectSources, $destination, $destination_base_url, $allowed_domains);
      }
      else {
        // Redirect could not be created log the issue.
        // @TODO  Connect this to a logger.
      }
    }
  }

  /**
   * Determines and makes the destination URL of an entity.
   *
   * @param int $entityId
   *   The entity id of the item that was migrated.
   *
   * @return string
   *   An entity path (node/123) or a file path (/sites/default/files/samp.pdf).
   */
  protected function createDestination(int $entityId) {
    $destination_uri = '';
    // Determine the entity type.
    if ((!empty($entityId))
      && !empty($this->getRedirectSetting('destination_entity'))) {
      // There is a destination so lets pick out the entity type.
      $destination_entity = $this->getRedirectSetting('destination_entity');
      $destination_file_field = $this->getRedirectSetting('destination_file_field');
      if (!empty($destination_entity)) {
        if (($destination_entity === 'media') && (!empty($destination_file_field))) {
          // The request is to build the redirect to the file.
          $destination_uri = self::getFileUriFromMedia($entityId, $destination_file_field);
        }
        // If the destination has not already been set, use the entity path.
        $destination_uri = empty($destination_uri) ? "{$destination_entity}/{$entityId}" : $destination_uri;
      }
    }
    return $destination_uri;
  }

  /**
   * Gets the path of a file that is  referenced in a media entity.
   *
   * @param int $entityId
   *   The media entity id.
   * @param string $destination_file_field
   *   The file field in the media entity.
   *
   * @return string
   *   The root relative path to a file referenced by the media field.
   */
  public static function getFileUriFromMedia(int $entityId, string $destination_file_field) {
    $path = '';
    if (!empty($entityId) && !empty($destination_file_field)) {
      $media_entity = Media::load($entityId);
      $file_id = $media_entity->get($destination_file_field)->target_id;
      if (!empty($file_id)) {
        $file_object = File::load($file_id);
        $file_uri = $file_object->getFileUri();
        $path = \Drupal::service('file_url_generator')->generateString($file_uri);
      }
    }

    return $path;
  }

  /**
   * Deletes any redirects associated files attached to an entity's file field.
   *
   * @param object $entity
   *   The fully loaded entity.
   * @param string $field_name
   *   The machine name of the attachment field.
   * @param string $language
   *   Optional. Defaults to LANGUAGE_NONE.
   */
  public function rollbackAttachmentRedirect($entity, $field_name, $language = '') {
    // @todo D8 Refactor
    $field = $entity->$field_name;
    if (!empty($field[$language])) {
      foreach ($field[$language] as $delta => $item) {
        $file = File::load($item['fid']);
        $url = \Drupal::service('file_url_generator')->generateAbsoluteString($file->uri);
        $parsed_url = parse_url($url);
        $destination = ltrim($parsed_url['path'], '/');
        redirect_delete_by_path($destination);
      }
    }
  }

  /**
   * Creates redirects for files attached to a given entity's field.
   *
   * @param object $entity
   *   The fully loaded entity.
   * @param array $source_urls
   *   A flat array of source urls that should redirect to the attachments
   *   on this entity. $source_urls[0] will redirect to the first attachment,
   *   $entity->$field_name[$language][0], and so on.
   * @param string $field_name
   *   The machine name of the attachment field.
   * @param string $language
   *   Optional. Defaults to LANGUAGE_NONE.
   */
  public function createAttachmentRedirect($entity, array $source_urls, $field_name, $language = LANGUAGE_NONE) {
    // @todo D8 Refactor
    if (empty($source_urls)) {
      // Nothing to be done here.
      $json_entity = json_encode($entity);
      watchdog("migration_tools", "redirect was not created for attachment in entity {$json_entity}");
      return;
    }

    $field = $entity->$field_name;
    if (!empty($field[$language])) {
      foreach ($field[$language] as $delta => $item) {
        // $file = file_load($item['fid']);
        // $url = file_create_url($file->uri);
        // $parsed_url = parse_url($url);
        // $destination = ltrim($parsed_url['path'], '/');.
        $source = $source_urls[$delta];

        // Create redirect.
        $redirect = redirect_load_by_source($source);
        if (!$redirect) {
          $redirect = new \stdClass();
          redirect_object_prepare($redirect);
          $redirect->source = $source;
          $redirect->redirect = "file/{$item['fid']}/download";
          redirect_save($redirect);
        }
      }
    }
  }

  /**
   * Retrieves server or html redirect of the page if it the destination exists.
   *
   * @param object $row
   *   A row object as delivered by migrate.
   * @param object $query_path
   *   The current QueryPath object.
   * @param array $redirect_texts
   *   (Optional) array of human readable strings that precede a link to the
   *   New location of the page ex: "this page has move to".
   *
   * @return mixed
   *   string - full URL of the validated redirect destination.
   *   string 'skip' if there is a redirect but it's broken.
   *   FALSE - no detectable redirects exist in the page.
   *
   * @throws \Drupal\migrate\MigrateException
   */
  public function hasValidRedirect($row, $query_path, array $redirect_texts = []) {
    // @TODO refactor for D8 and use setting from RedirectSettings.
    if (empty($row->pathing->legacyUrl)) {
      throw new MigrateException('$row->pathing->legacyUrl must be defined to look for a redirect.');
    }
    else {
      // Look for server side redirect.
      $server_side = $this->hasServerSideRedirects($row->pathing->legacyUrl);
      if ($server_side) {
        // A server side redirect was found.
        return $server_side;
      }
      else {
        // Look for html redirect.
        return self::hasValidHtmlRedirect($row, $query_path, $redirect_texts);
      }
    }
  }

  /**
   * Retrieves redirects from the html of the page if it the destination exists.
   *
   * @param object $row
   *   A row object as delivered by migrate.
   * @param object $query_path
   *   The current QueryPath object.
   * @param array $redirect_texts
   *   (Optional) array of human readable strings that precede a link to the
   *   New location of the page ex: "this page has move to".
   *
   * @return mixed
   *   string - full URL of the validated redirect destination.
   *   string 'skip' if there is a redirect but it's broken.
   *   FALSE - no detectable redirects exist in the page.
   */
  public function hasValidHtmlRedirect($row, $query_path, array $redirect_texts = []) {
    $destination = self::getRedirectFromHtml($row, $query_path, $redirect_texts);
    if ($destination) {
      // This page is being redirected via the page.
      // Is the destination still good?
      $real_destination = self::urlExists($destination);
      if ($real_destination) {
        // The destination is good. Message and return.
        $message = "Found redirect in html -> !destination";
        $variables = ['!destination' => $real_destination];
        Message::make($message, $variables, FALSE, 2);

        return $destination;
      }
      else {
        // The destination is not functioning. Message and bail with 'skip'.
        $message = "Found broken redirect in html-> !destination";
        $variables = ['!destination' => $destination];
        Message::make($message, $variables, Message::ERROR, 2);

        return 'skip';
      }
    }
    else {
      // No redirect destination found.
      return FALSE;
    }
  }

  /**
   * Check for server side redirects.
   *
   * @param string $url
   *   The full URL to a live page.
   *   Ex: https://www.oldsite.com/section/blah/index.html,
   *   https://www.oldsite.com/section/blah/.
   *
   * @return mixed
   *   string Url of the final destination if there was a redirect.
   *   bool FALSE if there was no redirect.
   */
  public function hasServerSideRedirects($url) {
    $final_url = Url::urlExists($url, TRUE);
    if ($final_url && ($url === $final_url)) {
      // The initial and final urls are the same, so no redirects.
      return FALSE;
    }
    else {
      // The $final_url is different, so it must have been redirected.
      return $final_url;
    }
  }

  /**
   * Retrieves redirects from the html of the page (meta, javascript, text).
   *
   * @param object $row
   *   A row object as delivered by migrate.
   * @param object $query_path
   *   The current QueryPath object.
   * @param array $redirect_texts
   *   (Optional) array of human readable strings that precede a link to the
   *   New location of the page ex: "this page has move to".
   *
   * @return mixed
   *   string - full URL of the redirect destination.
   *   FALSE - no detectable redirects exist in the page.
   */
  public function getRedirectFromHtml($row, $query_path, array $redirect_texts = []) {
    // Hunt for <meta> redirects via refresh and location.
    // These use only full URLs.
    $metas = $query_path->top()->find('meta');
    foreach (is_array($metas) || is_object($metas) ? $metas : [] as $meta) {
      $attributes = $meta->attr();
      $http_equiv = (!empty($attributes['http-equiv'])) ? strtolower($attributes['http-equiv']) : FALSE;
      if (($http_equiv === 'refresh') || ($http_equiv === 'location')) {
        // It has a meta refresh or meta location specified.
        // Grab the url from the content attribute.
        if (!empty($attributes['content'])) {
          $content_array = preg_split('/url=/i', $attributes['content'], -1, PREG_SPLIT_NO_EMPTY);
          // The URL is going to be the last item in the array.
          $url = trim(array_pop($content_array));
          if (filter_var($url, FILTER_VALIDATE_URL)) {
            // Seems to be a valid URL.
            return $url;
          }
        }
      }
    }

    // Hunt for Javascript redirects.
    // Checks for presence of Javascript. <script type="text/javascript">.
    $js_scripts = $query_path->top()->find('script');
    foreach (is_array($js_scripts) || is_object($js_scripts) ? $js_scripts : [] as $js_script) {
      $script_text = $js_script->text();
      $url = self::extractUrlFromJS($script_text);
      if ($url) {
        return $url;
      }
    }

    // Try to account for jQuery redirects like:
    // onLoad="setTimeout(location.href='http://www.newpage.com', '0')".
    // So many variations means we can't catch them all.  But try the basics.
    $body_html = $query_path->top()->find('body')->html();
    $search = 'onLoad=';
    $content_array = preg_split("/$search/", $body_html, -1, PREG_SPLIT_NO_EMPTY);
    // If something was found there will be > 1 element in the array.
    if (count($content_array) > 1) {
      // It had an onLoad, now check it for locations.
      $url = self::extractUrlFromJS($content_array[1]);
      if ($url) {
        return $url;
      }
    }

    // Check for human readable text redirects.
    foreach (is_array($redirect_texts) ? $redirect_texts : [] as $i => $redirect_text) {
      // Array of starts and ends to try locating.
      $wrappers = [];
      // Provide two elements: the beginning and end wrappers.
      $wrappers[] = ['"', '"'];
      $wrappers[] = ["'", "'"];
      foreach ($wrappers as $wrapper) {
        $body_html = $query_path->top()->find('body')->innerHtml();
        $url = self::peelUrl($body_html, $redirect_text, $wrapper[0], $wrapper[1]);
        if ($url) {
          return $url;
        }
      }
    }
  }

  /**
   * Checks and adds redirect if URL matches candidates for a default document.
   *
   * @param string $url
   *   The URL to be tested.
   * @param string $destination_base_url
   *   Destination base URL.
   * @param array $candidates
   *   A list of potential document names that could be indexes.
   *   Defaults to "default" and "index".
   *
   * @return mixed
   *   string - The base path if a matching document is found.
   *   bool - FALSE if no matching document is found.
   */
  public function addRedirectIfIndex($url, $destination_base_url, array $candidates = []) {
    $candidates = (empty($candidates)) ? $this->getRedirectSetting('index_filenames') : $candidates;
    if (!empty($candidates)) {
      // Process this to see if it is an index.
      // Filter through parse_url to separate out query strings and etc.
      $path = parse_url($url, PHP_URL_PATH);

      // Pull apart components of the file and path that we'll need to compare.
      $filename = strtolower(pathinfo($path, PATHINFO_FILENAME));
      $extension = pathinfo($path, PATHINFO_EXTENSION);
      $root_path = pathinfo($path, PATHINFO_DIRNAME);

      // Test parsed URL.
      if (!empty($filename) && !empty($extension) && in_array($filename, $candidates)) {
        // Build the new implied route (base directory plus any arguments).
        $new_url = Url::reassembleURL([
          'path' => $root_path,
          'query' => parse_url($url, PHP_URL_QUERY),
          'fragment' => parse_url($url, PHP_URL_FRAGMENT),
        ], $destination_base_url, FALSE);

        $this->addRedirectSource($new_url);
        return $new_url;
      }
    }
    // Default to returning FALSE if we haven't exited already.
    return FALSE;
  }

}
