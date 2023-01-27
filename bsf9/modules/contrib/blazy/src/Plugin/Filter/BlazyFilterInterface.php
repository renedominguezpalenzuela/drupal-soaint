<?php

namespace Drupal\blazy\Plugin\Filter;

/**
 * Defines re-usable services and functions for blazy plugins.
 */
interface BlazyFilterInterface {

  /**
   * Gets the caption if available.
   *
   * @param array $build
   *   The content array being modified.
   * @param object $node
   *   The HTML DOM object.
   *
   * @return object
   *   The HTML DOM object.
   */
  public function buildImageCaption(array &$build, &$node);

  /**
   * Returns the faked image item from SRC.
   *
   * @param array $build
   *   The content array being modified: item, settings.
   * @param object $node
   *   The HTML DOM object.
   * @param string $src
   *   The corrected SRC value.
   *
   * @return object
   *   The faked or file entity image item.
   */
  public function getImageItemFromImageSrc(array &$build, $node, $src);

  /**
   * Returns the faked image item from SRC.
   *
   * @param array $build
   *   The content array being modified: item, settings.
   * @param object $node
   *   The HTML DOM object.
   * @param string $src
   *   The corrected SRC value.
   *
   * @return object
   *   The faked or file entity image item.
   */
  public function getImageItemFromIframeSrc(array &$build, &$node, $src);

  /**
   * Returns the main settings.
   *
   * @param string $text
   *   The provided text.
   *
   * @return array
   *   The main settings for current filter.
   */
  public function buildSettings($text);

}
