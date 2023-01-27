<?php

namespace Drupal\views_migration\Plugin\migrate\views\access\d7;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\views_migration\Plugin\migrate\views\MigrateViewsPluginPluginBase;

/**
 * The default Migrate Views Access plugin.
 *
 * This plugin is used to prepare the Views `access` display options for
 * migration when no other migrate plugin exists for the current access plugin.
 *
 * @MigrateViewsAccess(
 *   id = "d7_default",
 *   core = {7},
 * )
 */
class DefaultAccess extends MigrateViewsPluginPluginBase {

  /**
   * {@inheritdoc}
   */
  public function prepareDisplayOptions(array &$display_options) {
    if (isset($display_options['access']['type']) && !in_array($display_options['access']['type'], $this->pluginList['access'], TRUE)) {
      $message = sprintf("The access plugin '%s' does not exist. Replaced with the 'none' plugin.", $display_options['access']['type']);
      $this->saveMessage($message, MigrationInterface::MESSAGE_WARNING);
      $display_options['access'] = [
        'type' => 'none',
      ];
    }
  }

}
