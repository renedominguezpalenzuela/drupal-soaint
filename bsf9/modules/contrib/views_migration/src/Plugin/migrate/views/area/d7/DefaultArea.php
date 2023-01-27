<?php

namespace Drupal\views_migration\Plugin\migrate\views\area\d7;

use Drupal\views_migration\Plugin\migrate\views\MigrateViewsHandlerPluginBase;

/**
 * The default Migrate Views Area plugin.
 *
 * This plugin is used to prepare the Views `area` display options for
 * migration when no other migrate plugin exists for the current area plugin.
 *
 * @MigrateViewsArea(
 *   id = "d7_default",
 *   core = {7},
 * )
 */
class DefaultArea extends MigrateViewsHandlerPluginBase {

  /**
   * {@inheritdoc}
   */
  public function alterHandlerConfig(array &$handler_config) {
    // @todo Why is this being done? Can it be removed?
    if ($handler_config['field'] === 'view' && isset($handler_config['view_to_insert'])) {
      $handler_config['plugin_id'] = 'migration_view';
      $handler_config['field'] = 'migration_view';
    }
  }

}
