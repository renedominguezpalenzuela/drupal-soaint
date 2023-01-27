<?php

namespace Drupal\views_migration\Plugin\migrate\views\cache\d7;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\views_migration\Plugin\migrate\views\MigrateViewsPluginPluginBase;

/**
 * The default Migrate Views Cache plugin.
 *
 * This plugin is used to prepare the Views `cache` display options for
 * migration when no other migrate plugin exists for the current cache plugin.
 *
 * @MigrateViewsCache(
 *   id = "d7_default",
 *   core = {7},
 * )
 */
class DefaultCache extends MigrateViewsPluginPluginBase {

  /**
   * {@inheritdoc}
   */
  public function prepareDisplayOptions(array &$display_options) {
    if (isset($display_options['cache']['type']) && !in_array($display_options['cache']['type'], $this->pluginList['cache'], TRUE)) {
      $message = sprintf("The cache plugin '%s' does not exist. Replaced with the 'none' plugin.", $display_options['cache']['type']);
      $this->saveMessage($message, MigrationInterface::MESSAGE_WARNING);
      $display_options['cache'] = [
        'type' => 'none',
      ];
    }
  }

}
