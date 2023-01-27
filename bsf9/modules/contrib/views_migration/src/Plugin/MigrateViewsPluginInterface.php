<?php

namespace Drupal\views_migration\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Provides an interface for plugins responsible for migrating Views Plugins.
 *
 * The migration of the following Views Plugin types is supported:
 *   - access
 *   - cache
 *   - display
 *   - exposed_form
 *   - pager
 *   - query
 *   - row
 *   - style.
 */
interface MigrateViewsPluginInterface extends PluginInspectionInterface {

  /**
   * Prepare the Display Options related to the Views Plugin being migrated.
   *
   * @param array $display_options
   *   The current Views Display's display options. Passed by reference.
   */
  public function prepareDisplayOptions(array &$display_options);

}
