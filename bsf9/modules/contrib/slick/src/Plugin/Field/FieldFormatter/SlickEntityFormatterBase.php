<?php

namespace Drupal\slick\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
// @todo enabled post Blazy:2.10:
// use Drupal\blazy\Field\BlazyEntityVanillaBase;
use Drupal\blazy\Dejavu\BlazyEntityBase as BlazyEntityVanillaBase;
use Drupal\slick\SlickDefault;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for slick entity reference formatters without field details.
 *
 * @see \Drupal\slick_paragraphs\Plugin\Field\FieldFormatter
 * @see \Drupal\slick_entityreference\Plugin\Field\FieldFormatter
 */
abstract class SlickEntityFormatterBase extends BlazyEntityVanillaBase {

  use SlickFormatterTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    return self::injectServices($instance, $container, 'entity');
  }

  /**
   * Returns the blazy manager.
   */
  public function blazyManager() {
    return $this->formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return ['view_mode' => ''] + SlickDefault::baseSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $entities = $this->getEntitiesToView($items, $langcode);

    // Early opt-out if the field is empty.
    if (empty($entities)) {
      return [];
    }

    return $this->commonViewElements($items, $langcode, $entities);
  }

}
