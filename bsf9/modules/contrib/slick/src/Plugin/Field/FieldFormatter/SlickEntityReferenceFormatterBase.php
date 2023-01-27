<?php

namespace Drupal\slick\Plugin\Field\FieldFormatter;

use Drupal\blazy\Dejavu\BlazyEntityReferenceBase;
// @todo enable post Blazy:2.10:
// use Drupal\blazy\Field\BlazyEntityReferenceBase;
// use Drupal\blazy\Field\BlazyField;
use Drupal\slick\SlickDefault;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for slick entity reference formatters with field details.
 *
 * @see \Drupal\slick_media\Plugin\Field\FieldFormatter
 * @see \Drupal\slick_paragraphs\Plugin\Field\FieldFormatter
 */
abstract class SlickEntityReferenceFormatterBase extends BlazyEntityReferenceBase {

  use SlickFormatterTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    return self::injectServices($instance, $container, 'entity');
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return SlickDefault::extendedSettings() + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function buildElementThumbnail(array &$build, $element, $entity, $delta) {
    // The settings in $element has updated metadata extracted from media.
    $settings  = $element['settings'];
    $item_id   = 'slide';
    $view_mode = $settings['view_mode'] ?? '';
    $caption   = $settings['thumbnail_caption'] ?? NULL;

    if (!empty($settings['nav'])) {
      // Thumbnail usages: asNavFor pagers, dot, arrows, photobox thumbnails.
      $element[$item_id] = empty($settings['thumbnail_style'])
        ? [] : $this->formatter->getThumbnail($settings, $element['item']);

      $element['caption'] = $caption
        // @todo enable post Blazy:2.10:
        // BlazyField::view($entity, $caption, $view_mode)
        ? $this->blazyEntity->getFieldRenderable($entity, $caption, $view_mode)
        : [];

      $build['thumb']['items'][$delta] = $element;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getPluginScopes(): array {
    $_texts = ['text', 'text_long', 'string', 'string_long', 'link'];
    $texts  = $this->getFieldOptions($_texts);

    return [
      'thumb_captions'  => $texts,
      'thumb_positions' => TRUE,
      'nav'             => TRUE,
    ] + parent::getPluginScopes();
  }

}
