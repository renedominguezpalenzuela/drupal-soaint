<?php

namespace Drupal\migration_tools\Plugin\migrate_plus\data_parser;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Row;
use Drupal\migrate_plus\DataParserPluginBase;
use Drupal\migration_tools\Obtainer\Job;
use Drupal\migration_tools\Operations;
use Drupal\migration_tools\SourceParser\HtmlBase;
use Drupal\migration_tools\SourceParser\Node;
use GuzzleHttp\Exception\RequestException;

/**
 * Obtain HTML DOM data for migration.
 *
 * @DataParser(
 *   id = "dom",
 *   title = @Translation("DOM")
 * )
 */
class Dom extends DataParserPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The request headers passed to the data fetcher.
   *
   * @var array
   */
  protected $headers = [];

  /**
   * Iterator over the data.
   *
   * @var \Iterator
   */
  protected $iterator;

  /**
   * Get the source data for reading.
   *
   * @param string $url
   *   The URL to read the source data from.
   *
   * @return array
   *   Array of DOM data.
   *
   * @throws \Drupal\migrate\MigrateException
   */
  protected function getSourceIterator($url) {
    try {
      $row = new Row(['url' => $url]);
      $dom_config = $this->configuration['dom_config'];
      if (empty($dom_config)) {
        throw new MigrateException('No migration_tools settings under dom_config');
      }

      $dom_migration_tools_settings = $dom_config['migration_tools'];

      // Override source/source_type to use url inserted into Row object.
      $dom_migration_tools_settings[0]['source'] = 'url';
      $dom_migration_tools_settings[0]['source_type'] = 'url';
      Operations::process($dom_migration_tools_settings, $row);

      $data = $row->getSource();
      if (empty($this->itemSelector) || empty($data[$this->itemSelector])) {
        throw new MigrateException($this->t('No item_selector, or field @item_selector not found!', [ '@item_selector' => $this->itemSelector ]));
      }

      // Make sub-array elements available directly.
      // IDs don't support array elements.
      // @todo This may cause some key conflicts.
      $iterator_array = [];
      foreach ($data[$this->itemSelector] as $key => $item) {
        if (is_array($item)) {
          $iterator_array[] = array_merge([$this->itemSelector => $item], $item);
        }
        else {
          $iterator_array[] = [$this->itemSelector => $item];
        }
      }

      return $iterator_array;
    }
    catch (RequestException $e) {
      throw new MigrateException($e->getMessage(), $e->getCode(), $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function openSourceUrl($url) {
    $data = $this->getSourceIterator($url);
    if ($data) {
      $this->iterator = new \ArrayIterator($data);
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function fetchNextRow() {
    $current = $this->iterator->current();
    if ($current) {
      $this->currentItem = $current;
      $this->iterator->next();
    }
  }

}
