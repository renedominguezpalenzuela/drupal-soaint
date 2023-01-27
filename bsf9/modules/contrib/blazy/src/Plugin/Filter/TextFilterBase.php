<?php

namespace Drupal\blazy\Plugin\Filter;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Render\FilteredMarkup;
use Drupal\blazy\Blazy;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides base text or imageless filter utilities.
 */
abstract class TextFilterBase extends FilterBase implements ContainerFactoryPluginInterface {

  /**
   * The app root.
   *
   * @var string
   */
  protected $root;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Filter manager.
   *
   * @var \Drupal\filter\FilterPluginManager
   */
  protected $filterManager;

  /**
   * The blazy manager service.
   *
   * @var \Drupal\blazy\BlazyManagerInterface
   */
  protected $blazyManager;

  /**
   * The filter HTML plugin.
   *
   * @var \Drupal\filter\Plugin\Filter\FilterHtml
   */
  protected $htmlFilter;

  /**
   * The langcode.
   *
   * @var string
   */
  protected $langcode;

  /**
   * The result.
   *
   * @var \Drupal\filter\FilterProcessResult
   */
  protected $result;

  /**
   * The excluded settings to fetch from attributes.
   *
   * @var array
   */
  protected $excludedSettings = ['filter_tags'];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);

    $instance->root = Blazy::root($container);
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->filterManager = $container->get('plugin.manager.filter');
    $instance->blazyManager = $container->get('blazy.manager');

    return $instance;
  }

  /**
   * Extracts setting from attributes.
   */
  protected function extractSettings(\DOMElement $node, array &$settings) {
    $blazies = $settings['blazies'];

    // Ensures these settings are re-checked.
    $blazies->set('was.initialized', FALSE);

    if ($check = $node->getAttribute('settings')) {
      $check = str_replace("'", '"', $check);
      $check = Json::decode($check);
      if ($check) {
        $settings = array_merge($settings, $check);
      }
    }

    // Merge all defined attributes into settings for convenient.
    $defaults = $this->defaultConfiguration()['settings'] ?? [];
    if ($defaults) {
      foreach ($defaults as $key => $value) {
        if (in_array($key, $this->excludedSettings)) {
          continue;
        }

        $type = gettype($value);

        if ($node->hasAttribute($key)) {
          $node_value = $node->getAttribute($key);
          settype($node_value, $type);
          $settings[$key] = $node_value;
        }
      }
    }

    if (isset($settings['count'])) {
      $blazies->set('count', $settings['count']);
    }

    BlazyFilterUtil::toGrid($node, $settings);
  }

  /**
   * Return sanitized caption, stolen from Filter caption.
   */
  protected function filterHtml($text) {
    // Read the data-caption attribute's value, then delete it.
    $caption = Html::escape($text);

    // Sanitize caption: decode HTML encoding, limit allowed HTML tags; only
    // allow inline tags that are allowed by default, plus <br>.
    $caption = Html::decodeEntities($caption);
    $filtered_caption = $this->htmlFilter->process($caption, $this->langcode);

    if (isset($this->result)) {
      $this->result->addCacheableDependency($filtered_caption);
    }

    return FilteredMarkup::create($filtered_caption->getProcessedText());
  }

  /**
   * Prepares the settings.
   */
  protected function preSettings(array &$settings, $text) {
    if (!isset($this->htmlFilter)) {
      $this->htmlFilter = $this->filterManager->createInstance('filter_html', [
        'settings' => [
          'allowed_html' => '<a href hreflang target rel> <em> <strong> <b> <i> <cite> <code> <br>',
          'filter_html_help' => FALSE,
          'filter_html_nofollow' => FALSE,
        ],
      ]);
    }
  }

  /**
   * Modifies the settings.
   */
  protected function postSettings(array &$settings) {
    // Do nothing.
  }

  /**
   * Render the output.
   */
  protected function render(\DOMElement $node, array $output) {
    $dom = $node->ownerDocument;
    $altered_html = $this->blazyManager->getRenderer()->render($output);

    // Load the altered HTML into a new DOMDocument, retrieve element.
    $updated_nodes = Html::load($altered_html)->getElementsByTagName('body')
      ->item(0)
      ->childNodes;

    foreach ($updated_nodes as $updated_node) {
      // Import the updated from the new DOMDocument into the original
      // one, importing also the child nodes of the updated node.
      $updated_node = $dom->importNode($updated_node, TRUE);
      $node->parentNode->insertBefore($updated_node, $node);
    }

    // Finally, remove the original blazy node.
    if ($node->parentNode) {
      $node->parentNode->removeChild($node);
    }
  }

}
