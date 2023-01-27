<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the `Blazy File` or `Blazy Image` for Blazy only.
 *
 * @see \Drupal\blazy\Plugin\Field\FieldFormatter\BlazyFileFormatter
 * @see \Drupal\blazy\Plugin\Field\FieldFormatter\BlazyImageFormatter
 */
class BlazyFormatterBlazy extends BlazyFileFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $files = $this->getEntitiesToView($items, $langcode);

    // Early opt-out if the field is empty.
    if (empty($files)) {
      return [];
    }

    return $this->commonViewElements($items, $langcode, $files);
  }

  /**
   * {@inheritdoc}
   */
  public function buildElements(array &$build, $files) {
    foreach ($this->getElements($build, $files) as $delta => $element) {
      $build[] = $this->formatter->getBlazy($element, $delta);
    }
  }

}
