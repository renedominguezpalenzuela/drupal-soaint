<?php

namespace Drupal\views_migration\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * An interface for plugins which alter a Views Handler's Text Format type.
 *
 * These plugins essentially update the old text format type to the new type
 * used in the newer version of Drupal.
 */
interface MigrateViewsHandlerTextFormatInterface extends PluginInspectionInterface {

  /**
   * Provides the new text format type based on the source type.
   *
   * @param string $source_format
   *   The source text format.
   *
   * @return string
   *   The new text format type (or old type if there is no change
   *   required).
   */
  public function getNewTextFormat(string $source_format);

}
