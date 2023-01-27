<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;

/**
 * A Trait common for all blazy, including its sub-modules, text formatters.
 *
 * By-passed routines at BlazyFormatter designed for Image, Media, entities.
 * Bp-passed theme_[blazy|slick|splide|gridstack|mason|etc.]() routines for
 * more relevant themes/ types like processed_text, or others.
 */
trait BlazyFormatterViewBaseTrait {

  /**
   * Returns base view elements.
   */
  protected function baseViewElements(
    FieldItemListInterface $items,
    $langcode,
    array $settings = []
  ): array {
    // Early opt-out if the field is empty.
    if ($items->isEmpty()) {
      return [];
    }

    // Collects specific settings to this formatter.
    $defaults = $this->buildSettings();
    $settings = $settings ? array_merge($defaults, $settings) : $defaults;

    $this->preSettings($settings, $langcode);

    // Build the settings.
    $build = ['settings' => $settings];

    // @todo re-check if to call BlazyFormatter::buildSettings() instead.
    $this->formatter->fieldSettings($build, $items);

    // Build the elements.
    $this->buildElements($build, $items, $langcode);

    // Pass to manager for easy updates to all Blazy ecosystem formatters.
    $output = $this->manager->build($build);

    // Return without field markup, if not so configured, else field.html.twig.
    return empty($settings['use_theme_field']) ? $output : [$output];
  }

  /**
   * Prepare the settings, allows sub-modules to re-use and override.
   */
  protected function preSettings(array &$settings, $langcode = NULL): void {
    $blazies = $settings['blazies'];
    $blazies->set('language.code', $langcode);
  }

}
