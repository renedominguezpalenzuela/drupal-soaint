<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;

/**
 * A Trait common for all blazy, including its sub-modules, formatters.
 *
 * Since 2.9 this can replace and remove sub-module FormatterViewTrait anytime
 * for Media or Entity related formatters. For basic texts, use
 * self::baseViewElements() instead to by-pass
 * theme_[blazy|slick|splide|gridstack|mason|etc.]() routines.
 */
trait BlazyFormatterViewTrait {

  // Import once for very minimal difference.
  use BlazyFormatterViewBaseTrait;

  /**
   * Returns similar view elements across sub-modules.
   */
  protected function commonViewElements(
    FieldItemListInterface $items,
    $langcode,
    array $entities = [],
    array $settings = []
  ) {
    // Modifies settings before building elements.
    $entities = empty($entities) ? [] : array_values($entities);
    $elements = $entities ?: $items;

    // Early opt-out if the field is empty, and also entities are empty.
    // Entities might not be empty even if items are when defaults are provided.
    // Specific to file, media, entity_reference, this was checked upstream.
    // Only needed during transition to Blazy:3.x for sub-modules BC.
    // This can be removed when sub-modules have all extended Blazy view at 3.x.
    if (empty($elements)) {
      return [];
    }

    // Collects specific settings to this formatter.
    $defaults = $this->buildSettings();
    $settings = $settings ? array_merge($defaults, $settings) : $defaults;

    $this->preSettings($settings, $langcode);

    // Build the settings.
    $build = ['settings' => $settings];

    // Build the elements.
    $this->formatter->preBuildElements($build, $items, $entities);

    $this->buildElements($build, $elements, $langcode);

    // Modifies settings post building elements.
    $this->formatter->postBuildElements($build, $items, $entities);

    // Pass to manager for easy updates to all Blazy formatters.
    $output = $this->manager->build($build);

    // Return without field markup, if not so configured, else field.html.twig.
    return empty($settings['use_theme_field']) ? $output : [$output];
  }

}
