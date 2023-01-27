<?php

namespace Drupal\views_migration\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\views_migration\Plugin\migrate\views\SourceHandlerInfoProvider;

/**
 * An interface for plugins which alter a Views Handler's Table value.
 *
 * These plugins essentially update the old table value to the new value used
 * in the newer version of Drupal.
 */
interface MigrateViewsHandlerTableInterface extends PluginInspectionInterface {

  /**
   * Provides the new Handler Table value based on the source Handler's info.
   *
   * @param \Drupal\views_migration\Plugin\migrate\views\SourceHandlerInfoProvider $info_provider
   *   Provides info about the current Views Handler being migrated.
   *
   * @return string
   *   The new Views Handler Table value (or old value if there is no change
   *   required).
   */
  public function getNewTableValue(SourceHandlerInfoProvider $info_provider);

}
