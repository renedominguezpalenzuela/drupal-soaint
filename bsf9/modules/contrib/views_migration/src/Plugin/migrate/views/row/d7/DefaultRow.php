<?php

namespace Drupal\views_migration\Plugin\migrate\views\row\d7;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\views_migration\Plugin\migrate\views\MigrateViewsPluginPluginBase;

/**
 * The default Migrate Views Row plugin.
 *
 * This plugin is used to prepare the Views `row` display options for
 * migration when no other migrate plugin exists for the current row plugin.
 *
 * @MigrateViewsRow(
 *   id = "d7_default",
 *   core = {7},
 * )
 */
class DefaultRow extends MigrateViewsPluginPluginBase {

  /**
   * {@inheritdoc}
   */
  public function prepareDisplayOptions(array &$display_options) {
    if (isset($display_options['row_plugin'])) {
      if (!in_array($display_options['row_plugin'], $this->pluginList['row'], TRUE)) {
        $message = sprintf("The row plugin '%s' does not exist. Replaced with the 'fields' plugin.", $display_options['row_plugin']);
        $this->saveMessage($message, MigrationInterface::MESSAGE_WARNING);
        $display_options['row_plugin'] = 'fields';
      }
      $display_options['row']['type'] = $display_options['row_plugin'];
      if (isset($display_options['row_options'])) {
        $display_options['row']['options'] = $display_options['row_options'];
      }
    }
    if (isset($display_options['defaults'])) {
      if (array_key_exists('row_plugin', $display_options['defaults']) && $display_options['defaults']['row_plugin'] === FALSE) {
        $display_options['defaults']['row'] = FALSE;
        unset($display_options['defaults']['row_plugin']);
      }
      if (array_key_exists('row_options', $display_options['defaults']) && $display_options['defaults']['row_options'] === FALSE) {
        $display_options['defaults']['row'] = FALSE;
        unset($display_options['defaults']['row_options']);
      }
    }
    unset($display_options['row_plugin'], $display_options['row_options']);
  }

}
