<?php

namespace Drupal\views_migration\Plugin\migrate\views\style\d7;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\views_migration\Plugin\migrate\views\MigrateViewsPluginPluginBase;

/**
 * The default Migrate Views Style plugin.
 *
 * This plugin is used to prepare the Views `style` display options for
 * migration when no other migrate plugin exists for the current style plugin.
 *
 * @MigrateViewsStyle(
 *   id = "d7_default",
 *   core = {7},
 * )
 */
class DefaultStyle extends MigrateViewsPluginPluginBase {

  /**
   * {@inheritdoc}
   */
  public function prepareDisplayOptions(array &$display_options) {
    if (isset($display_options['style_plugin'])) {
      $display_options['style']['type'] = $display_options['style_plugin'];
      if (isset($display_options['style_options'])) {
        $display_options['style']['options'] = $display_options['style_options'];
      }
      if (!in_array($display_options['style_plugin'], $this->pluginList['style'], TRUE)) {
        $message = sprintf("The style plugin '%s' does not exist. Replaced with the 'default' plugin.", $display_options['style_plugin']);
        $this->saveMessage($message, MigrationInterface::MESSAGE_WARNING);
        $display_options['style']['type'] = 'default';
      }
    }
    if (isset($display_options['defaults'])) {
      if (array_key_exists('style_plugin', $display_options['defaults']) && $display_options['defaults']['style_plugin'] === FALSE) {
        $display_options['defaults']['style'] = FALSE;
        unset($display_options['defaults']['style_plugin']);
      }
      if (array_key_exists('style_options', $display_options['defaults']) && $display_options['defaults']['style_options'] === FALSE) {
        $display_options['defaults']['style'] = FALSE;
        unset($display_options['defaults']['style_options']);
      }
    }
  }

}
