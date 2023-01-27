<?php

namespace Drupal\views_migration\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Provides an interface for plugins responsible for migrating Views Handlers.
 *
 * The migration of the following Views Handlers types is supported:
 *   - area (ie header, footer)
 *   - argument
 *   - field
 *   - filter
 *   - relationship
 *   - sort.
 */
interface MigrateViewsHandlerInterface extends PluginInspectionInterface {

  /**
   * Alter the configuration related to the Views Handler being migrated.
   *
   * @param array $handler_config
   *   The Views Handler Config to alter.
   */
  public function alterHandlerConfig(array &$handler_config);

}
