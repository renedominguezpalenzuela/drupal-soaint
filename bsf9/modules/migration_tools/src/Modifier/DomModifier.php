<?php

namespace Drupal\migration_tools\Modifier;

use Drupal\migration_tools\QpHtml;
use Drupal\migration_tools\StringTools;
use Drupal\migration_tools\Url;

/**
 * The DomModifier defining removers, and changers.
 *
 * Removers must:
 *   Be protected functions that remove elements from the QueryPath object.
 *   Return a count of the items removed.
 *   Follow the naming patten removeThingToBeRemoved().
 *
 * Changers must:
 *   Be protected functions that alter elements from the QueryPath object.
 *   Return a count of the items changed.
 *   Follow the naming patten changeDescriptionOfChange().
 */
class DomModifier extends Modifier {
  protected $queryPath;
  protected $row;
  protected $migrationToolsSettings;

  /**
   * Constructor.
   *
   * @param object $query_path
   *   The query path object by reference.
   * @param object $row
   *   The migrate row object by reference.
   */
  public function __construct(&$query_path = NULL, &$row = NULL) {
    $this->setQueryPath($query_path);
    $this->row = $row;
    $this->migrationToolsSettings = $this->row->getSourceProperty('migration_tools');
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
   * Getter for Migration Tools Redirect Settings.
   *
   * @param string $propertyName
   *   The name of the migration tool redirect setting property to return.
   *
   * @return mixed
   *   string || array: depending on what is in the property.
   *   array: defaults to empty array if no item exists for that property.
   */
  protected function getMigrationToolsRedirectSetting($propertyName) {
    $redirect_settings = $this->getMigrationToolsSetting('redirect');

    return (!empty($redirect_settings[$propertyName])) ? $redirect_settings[$propertyName] : [];
  }

  /**
   * Set Query Path.
   *
   * @param object $query_path
   *   Query Path.
   */
  public function setQueryPath(&$query_path) {
    $this->queryPath = $query_path;
  }

  /**
   * {@inheritdoc}
   */
  public function runModifier($method_name, array $arguments = []) {
    // Reset QueryPath pointer to top of document.
    $this->queryPath->top();
    return parent::runModifier($method_name, $arguments);
  }

  /**
   * Change any HTML class from one to a new classname.
   *
   * @param string $original_classname
   *   The classname to change from.
   * @param string $new_classname
   *   The new classname.  Removes the class if empty.
   *
   * @return int
   *   Count of items removed.
   */
  protected function changeClassName($original_classname, $new_classname = '') {
    $count = 0;
    if (!empty($original_classname)) {
      $elements = $this->queryPath->find(".{$original_classname}");
      foreach ((is_object($elements)) ? $elements : [] as $element) {
        if (empty($new_classname)) {
          $element->removeAttr('class');
        }
        else {
          $element->attr('class', $new_classname);
        }

        $count++;
      }
    }

    return $count;
  }

  /**
   * Remove a class from a class from all elements.
   *
   * @param string $classname
   *   The classname to remove.
   *
   * @return int
   *   Count of items removed.
   */
  protected function changeRemoveClassName($classname) {
    return $this->changeClassName($classname);
  }

  /**
   * Remove all tables that are empty or contain only whitespace.
   *
   * @return int
   *   Count of items removed.
   */
  protected function removeEmptyTables() {
    $count = 0;
    $tables = $this->queryPath->find('table');
    foreach ((is_object($tables)) ? $tables : [] as $table) {
      $table_contents = $table->text();
      // Remove whitespace in order to evaluate if it is empty.
      $table_contents = StringTools::superTrim($table_contents);

      if (empty($table_contents)) {
        $table->remove();
        $count++;
      }
    }

    return $count;
  }

  /**
   * Remover for all matching selector on the page.
   *
   * @param string $selector
   *   The selector to find.
   *
   * @return int
   *   Count of items removed.
   */
  protected function removeSelectorAll($selector) {
    $count = 0;
    if (!empty($selector)) {
      $elements = $this->queryPath->find($selector);
      foreach ((is_object($elements)) ? $elements : [] as $element) {
        $element->remove();
        $count++;
      }
    }

    return $count;
  }

  /**
   * Remover for Nth  selector on the page.
   *
   * @param string $selector
   *   The selector to find.
   * @param int $n
   *   (optional) The depth to find.  Default: first item n=1.
   *
   * @return int
   *   Count of items removed.
   */
  protected function removeSelectorN($selector, $n = 1) {
    $n = ($n > 0) ? $n - 1 : 0;
    if (!empty($selector)) {
      $elements = $this->queryPath->find($selector);
      foreach ((is_object($elements)) ? $elements : [] as $i => $element) {
        if ($i == $n) {
          $element->remove();

          return 1;
        }
      }
    }

    return 0;
  }

  /**
   * Remove style attribute from selector.
   *
   * @param object $selector
   *   The selector to find.
   *
   * @return int
   *   Count of style attributes removed.
   */
  protected function removeStyleAttr($selector) {
    $count = 0;
    if (!empty($selector)) {
      $elements = $this->queryPath->find($selector);
      foreach ((is_object($elements)) ? $elements : [] as $element) {
        $element->removeAttr('style');
        $count++;
      }
    }

    return $count;
  }

  /**
   * Like match, but removes all matching elements.
   *
   * @param string $selector
   *   The CSS selector for the element to be matched.
   * @param string $needle
   *   The text string for which to search.
   * @param string $method
   *   The function used to get the haystack. E.g., 'attr' if searching for
   *   a specific attribute value, 'html', 'txt'.
   * @param string $parameter
   *   A parameter to be passed into the defined $function.
   */
  public function matchRemoveAll($selector, $needle, $method, $parameter = NULL) {
    $matches = QpHtml::matchAll($this->queryPath, $selector, $needle, $method, $parameter);
    foreach ($matches as $match) {
      $match->remove();
    }
  }

  /**
   * Removes target and the next sibling.
   *
   * Target is determined by a test search restricted by selector and
   * optional index.
   *
   * @param string $selector
   *   The CSS selector for the element to be matched.
   * @param string $needle
   *   The text string for which to search.
   * @param string $method
   *   The function used to get the haystack. E.g., 'attr' if searching for
   *   a specific attribute value, 'html', 'txt'.
   * @param string $parameter
   *   A (optional) parameter to be passed into the defined $function.
   * @param int $index
   *   An (optional) index to restrict the search to a specific depth of the
   *   selector, zero-based.
   */
  public function removeMatchAndNextSibling($selector, $needle, $method, $parameter = NULL, $index = NULL) {
    $elements = $this->queryPath->find($selector);
    $counter = 0;
    foreach ($elements as $key => $elem) {
      // Limit the search if the index is specified, if not search them all.
      if ($counter++ == $index || is_null($index)) {
        $haystack = $elem->$method($parameter);
        if (substr_count($haystack, $needle) > 0) {
          // We have a match.  Take out the target and next sibling.
          $target = $elem;
          $elem->next()->remove();
          $target->remove();
        }
      }
    }
  }

  /**
   * Removes target and the next sibling.
   *
   * Target is determined by a case insensitive test search restricted by
   * selector and optional index.
   *
   * @param string $selector
   *   The CSS selector for the element to be matched.
   * @param string $needle
   *   The text string for which to search.
   * @param string $method
   *   The function used to get the haystack. E.g., 'attr' if searching for
   *   a specific attribute value, 'html', 'txt'.
   * @param string $parameter
   *   A (optional) parameter to be passed into the defined $function.
   * @param int $index
   *   An (optional) index to restrict the search to a specific depth of the
   *   selector, zero-based.
   */
  public function removeInsensitiveMatchAndNextSibling($selector, $needle, $method, $parameter = NULL, $index = NULL) {
    $needle = strtolower($needle);
    $elements = $this->queryPath->find($selector);
    $counter = 0;
    foreach ($elements as $key => $elem) {
      // Limit the search if the index is specified, if not search them all.
      if ($counter++ == $index || is_null($index)) {
        $haystack = $elem->$method($parameter);
        $haystack = strtolower($haystack);
        if (substr_count($haystack, $needle) > 0) {
          // We have a match.  Take out the target and next sibling.
          $target = $elem;
          $elem->next()->remove();
          $target->remove();
        }
      }
    }
  }

  /**
   * Examine all img longdesc attr in qp and remove any that point to images.
   */
  protected function removeFaultyImgLongdesc() {
    QpHtml::removeFaultyImgLongdesc($this->queryPath);
  }

  /**
   * Empty anchors without name attribute will be stripped by ckEditor.
   */
  protected function fixNamedAnchors() {
    QpHtml::fixNamedAnchors($this->queryPath);
  }

  /**
   * Removes all html comments from querypath document.
   */
  protected function removeComments() {
    QpHtml::removeComments($this->queryPath);
  }

  /**
   * Removes elements matching CSS selectors.
   *
   * @param array $selectors
   *   An array of selectors to remove.
   */
  protected function removeElements(array $selectors) {
    QpHtml::removeElements($this->queryPath, $selectors);
  }

  /**
   * Removes a wrapping element, leaving child elements intact.
   *
   * @param array $selectors
   *   An array of selectors for the wrapping element(s).
   */
  protected function removeWrapperElements(array $selectors) {
    QpHtml::removeWrapperElements($this->queryPath, $selectors);
  }

  /**
   * Removes elements matching CSS selectors.
   *
   * @param array $selectors
   *   An array of selectors for the wrapping element(s).
   *   pattern:  ['selector': new wrapper].
   *   new_wrapper is a string of the leading wrapping element.
   *   - <h2 />
   *   - <h2 id="title" />
   *   - <div class="friends" />.
   */
  protected function rewrapElements(array $selectors) {
    foreach ($selectors as $element => $new_wrapper) {
      // Make sure the array key is not just an array index.
      if (is_string($element) && !is_numeric($element)) {
        QpHtml::rewrapElements($this->queryPath, [$element], $new_wrapper);
      }
    }
  }

  /**
   * Convert all relative links to absolute if base href if set.
   */
  public function convertBaseHrefLinks() {
    // Get base href URL.
    $base = $this->queryPath->top('head')->find('base');
    if ($base) {
      $base_href = $base->attr('href');

      // Attributes to check for relative URLs.
      $attributes = [
        'href' => 'a[href], area[href]',
        'longdesc' => 'img[longdesc]',
        'src' => 'img[src], script[src], embed[src]',
        'value' => 'param[value]',

      ];
      foreach ($attributes as $attribute => $selector) {

        $file_links = $this->queryPath->top($selector);
        foreach ($file_links as $file_link) {
          $href = trim($file_link->attr($attribute));
          $href_pieces = parse_url($href);
          if (count($href_pieces) && empty($href_pieces['scheme'])) {
            // No scheme set, must be a relative internal URL.
            $new_href = $base_href;
            $new_href .= (!empty($href_pieces['path'])) ? ltrim($href_pieces['path'], '/') : '';
            $new_href .= (!empty($href_pieces['query'])) ? '?' . $href_pieces['query'] : '';
            $new_href .= (!empty($href_pieces['fragment'])) ? '#' . $href_pieces['fragment'] : '';
            // Remove the base_href to make link relative to root.
            $new_href = str_ireplace(rtrim($base_href, '/'), '', $new_href);

            $file_link->attr($attribute, $new_href);
          }
        }
      }
    }
  }

  /**
   * Convert all Relative HREFs in queryPath to Absolute.
   *
   * @param string $url
   *   Base URL.
   * @param string $destination_base_url
   *   Destination Base URL.
   * @param string $base_for_relative
   *   The base url to be used for determining relative links.
   */
  public function convertLinksAbsoluteSimple($url = '', $destination_base_url = '', $base_for_relative = '') {
    if (empty($url)) {
      // Determine the value another way.
      // Try the base tag.
      $base = $this->queryPath->top('head')->find('base')->attr('href');
      if ($base) {
        // Base is set, so use it as it is the best source of truth.
        $url = $base;
      }
      elseif ($this->row->hasSourceProperty('source_url') && !empty($this->row->getSourceProperty('source_url'))) {
        // The value comes from a specific migrate source property.
        $url = $this->row->getSourceProperty('source_url');
      }
      elseif ($this->getMigrationToolsSetting('source_type') == 'url' && !empty($this->getMigrationToolsSetting('source'))) {
        // Might be able to infer one from the MigrationTools source location.
        $this->addRedirectSource($this->getMigrationToolsSetting('source'));
        $sourceUrl = $this->getMigrationToolsSetting('source');
      }
    }

    $url_pieces = parse_url($url);
    $path = $url_pieces['path'];
    if (empty($base_for_relative)) {
      $base_for_relative = $url_pieces['scheme'] . '://' . $url_pieces['host'];
    }

    if (empty($destination_base_url)) {
      // Determine the value another way.
      // @TODO work this out to get it from settings or admin.
    }

    $mts_pathing = $this->getMigrationToolsSetting('pathing');
    if (!empty($mts_pathing)  && !empty($mts_pathing['domain_conversion'])) {
      $url_base_alters = $mts_pathing['domain_conversion'];
    }
    elseif ((!empty($url_pieces['host'])) && (!empty($destination_base_url))) {
      $url_base_alters = [$url_pieces['host'] => $destination_base_url];
    }
    else {
      $url_base_alters = [];
    }

    Url::rewriteImageHrefsOnPage($this->queryPath, $url_base_alters, $path, $base_for_relative, $destination_base_url);
    Url::rewriteAnchorHrefsToBinaryFiles($this->queryPath, $url_base_alters, $path, $base_for_relative, $destination_base_url);
    Url::rewriteScriptSourcePaths($this->queryPath, $url_base_alters, $path, $base_for_relative, $destination_base_url);
    Url::rewriteAnchorHrefsToPages($this->queryPath, $url_base_alters, $path, $base_for_relative, $destination_base_url);
  }

  /**
   * Clean extra tags from beginning/end/both of selector contents.
   *
   * @param array $tags
   *   Tags to search for.
   * @param string $selector
   *   Selector to clean.
   * @param string $where
   *   Defaults to 'both', accepts 'leading' and 'trailing'.
   */
  public function cleanExtraTags(array $tags, $selector, $where = 'both') {
    $element = $this->queryPath->find($selector);
    $html = $element->innerHTML();
    $html = StringTools::superTrim($html);

    if ($where == 'both' || $where == 'leading') {
      $html = preg_replace('#^' . implode('|^', $tags) . '#i', '', $html);
    }
    if ($where == 'both' || $where == 'trailing') {
      $html = preg_replace('#' . implode('$|', $tags) . '$#i', '', $html);
    }
    $element->html($html);
  }

  /**
   * Clean extra BR tags from beginning/end/both of selector contents.
   *
   * @param string $selector
   *   Selector to clean.
   * @param string $where
   *   Defaults to 'both', accepts 'leading' and 'trailing'.
   */
  public function cleanExtraBrTags($selector, $where = 'both') {
    // Normalize variations of the br tag.
    // @codingStandardsIgnoreStart
    $search = [
      '<br>',
      '<br />',
      '<br/>',
    ];
    // @codingStandardsIgnoreEnd
    self::cleanExtraTags($search, $selector, $where);
  }

  /**
   * Replace existing contents of a selector with new content.
   *
   * @param string $selector
   *   Selector to replace contents of.
   * @param string $new_content
   *   New content for $selector.
   *
   * @return int
   *   Count of elements changed.
   */
  protected function changeHtmlContents($selector, $new_content) {
    $count = 0;
    if (!empty($selector)) {
      $elements = $this->queryPath->find($selector);

      foreach ((is_object($elements)) ? $elements : [] as $element) {
        $element->html($new_content);
        $count++;
      }
    }

    return $count;
  }

  /**
   * Alter images that are relative (have no scheme or host) to media tokens.
   *
   * @param string $lookup_method
   *   Use 'redirect' - to look it up by an existing redirect.
   *   Use 'migrate_map' - To look it up by migrate key.
   * @param array $migrations_to_lookup
   *   (optional) Required if method is migration, the migrate machine name.
   * @param array $media_parameters
   *   Parameters that can be processed by media.
   */
  public function convertImageLinksToMedia(string $lookup_method, array $migrations_to_lookup = [], array $media_parameters = []) {
    $this->precheckLookupRequest($lookup_method, $migrations_to_lookup);
    $ignore_path = $this->getMigrationToolsRedirectSetting('source_namespace');
    $entity_lookup = [
      'method' => $lookup_method,
      'migrations' => $migrations_to_lookup,
      'ignore_path' => (!empty($ignore_path)) ? $ignore_path : '',
    ];
    Url::rewriteRelativeImageHrefsToMedia($this->queryPath, $entity_lookup, $media_parameters);
  }

  /**
   * Look up an file href and see if it is a media entity.
   *
   * @param string $lookup_method
   *   Use 'redirect' to look it up by an existing redirect.
   *   Use 'migrate-map' To look it up by migrate key.
   * @param string $migration_to_lookup
   *   (optional) Required if method is migration, the migrate machine name.
   */
  public function convertBinaryFileLinksToMedia(string $lookup_method, string $migration_to_lookup = '') {
    // @TODO flesh this out.
  }

  /**
   * Precheck the request to make sure the necessary parameters are present.
   *
   * @param string $lookup_method
   *   The method to lookup.
   * @param array $migrations_to_lookup
   *   (optional) Required if method is migration, the migrate machine name.
   *
   * @throws MigrateException
   *   Fails if the method is unknown or data is missing.
   */
  private function precheckLookupRequest(string &$lookup_method, array $migrations_to_lookup = []) {
    switch ($lookup_method) {
      case 'redirect':
        // Lookup in the redirect table.
        // No further checks needed.
        break;

      case 'migrate-map':
        // Wrong value but close enough.  Fix it and drop through.
        $lookup_method = 'migrate_map';

      case 'migrate_map':
        // Try to lookup by the migration key in the migrate_map.
        if (empty($migrations_to_lookup)) {
          throw new MigrateException("When looking up an entity by migrate_map, a migration id must be provided.");
        }

        break;

      default:
        throw new MigrateException("Invalid entity lookup_method:$lookup_method. Only allowed values are 'redirect' and 'migration'");

    }
  }

}
