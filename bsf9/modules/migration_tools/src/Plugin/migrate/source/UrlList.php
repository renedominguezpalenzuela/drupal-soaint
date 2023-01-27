<?php

namespace Drupal\migration_tools\Plugin\migrate\source;

use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Source for URL List.
 *
 * Uses configuration 'urls' as rows to retrieve.
 *
 * @MigrateSource(
 *   id = "url_list"
 * )
 */
class UrlList extends SourcePluginBase {

  /**
   * List of available source fields.
   *
   * Keys are the field machine names as used in field mappings, values are
   * descriptions.
   *
   * @var array
   */
  protected $fields = [];

  /**
   * List of key fields, as indexes.
   *
   * @var array
   */
  protected $keys = [];

  /**
   * URLs to process.
   *
   * @var array
   */
  protected $urls = [];

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);

    // Path is required.
    if (empty($this->configuration['urls'])) {
      throw new MigrateException('You must declare the "urls" in your source settings.');
    }

    $this->urls = $configuration['urls'];
  }

  /**
   * Return a string representing the source file path.
   *
   * @return string
   *   The file path.
   */
  public function __toString() {
    return implode(',', $this->urls);
  }

  /**
   * {@inheritdoc}
   */
  public function initializeIterator() {
    $urls = [];
    // Map URL to a key/value for iterator.
    foreach ($this->urls as $url) {
      $urls[] = ['url' => $url];
    }
    return new \ArrayIterator($urls);
  }

  /**
   * {@inheritdoc}
   */
  public function getIDs() {
    $ids['url']['type'] = 'string';
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields['urls'] = $this->urls;
    // Any caller-specified fields with the same names as extracted fields will
    // override them; any others will be added.
    if (!empty($this->configuration['fields'])) {
      $fields = $this->configuration['fields'] + $fields;
    }

    return $fields;
  }

}
