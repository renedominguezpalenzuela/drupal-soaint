<?php

namespace Drupal\views_migration\Plugin\migrate\views\pager\d7;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\views_migration\Plugin\migrate\views\MigrateViewsPluginPluginBase;

/**
 * The default Migrate Views Page plugin.
 *
 * This plugin is used to prepare the Views `pager` display options for
 * migration when no other migrate plugin exists for the current pager plugin.
 *
 * @MigrateViewsPager(
 *   id = "d7_default",
 *   core = {7},
 * )
 */
class DefaultPager extends MigrateViewsPluginPluginBase {

  /**
   * {@inheritdoc}
   */
  public function prepareDisplayOptions(array &$display_options) {
    if (isset($display_options['pager']['type']) && !in_array($display_options['pager']['type'], $this->pluginList['pager'], TRUE)) {
      $message = sprintf("The pager plugin '%s' does not exist. Replaced with the 'none' plugin.", $display_options['pager']['type']);
      $this->saveMessage($message, MigrationInterface::MESSAGE_WARNING);
      $display_options['pager'] = [
        'type' => 'none',
      ];
    }
  }

}
