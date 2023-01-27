<?php

namespace Drupal\views_migration\Plugin\migrate\views\display\d7;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\views_migration\Plugin\migrate\views\MigrateViewsPluginPluginBase;

/**
 * The default Migrate Views Display plugin.
 *
 * This plugin is used to prepare the Views `display` options for migration
 * when no other migrate plugin exists for the current display plugin.
 *
 * @MigrateViewsDisplay(
 *   id = "d7_default",
 *   core = {7},
 * )
 */
class DefaultDisplay extends MigrateViewsPluginPluginBase {

  /**
   * {@inheritdoc}
   */
  public function prepareDisplayOptions(array &$display_options) {
    if ($display_options['display_plugin'] !== 'default' && !in_array($display_options['display_plugin'], $this->pluginList['display'], TRUE)) {
      $message = sprintf("The display plugin '%s' does not exist. Replaced with the 'default' plugin.", $display_options['display_plugin']);
      $this->saveMessage($message, MigrationInterface::MESSAGE_WARNING);
      $display_options['display_plugin'] = 'default';
    }
  }

}
