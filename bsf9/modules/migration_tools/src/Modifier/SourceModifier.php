<?php

namespace Drupal\migration_tools\Modifier;

/**
 * SourceModifier abstract class for source operations.
 */
abstract class SourceModifier extends Modifier {
  protected $content;
  protected $contentUnaltered;

  /**
   * Constructor.
   *
   * @param string $content
   *   The contents to be modified.
   */
  public function __construct($content = NULL) {
    $this->content = $content;
    $this->contentUnaltered = $content;
  }

  /**
   * Get Content.
   *
   * @return null|string
   *   Content
   */
  public function getContent() {
    return $this->content;
  }

  /**
   * Get Unaltered Content.
   *
   * @return null|string
   *   Unaltered content.
   */
  public function getUnalteredContent() {
    return $this->contentUnaltered;
  }
}
