<?php

namespace Drupal\views_migration\Plugin\migrate\views\argument_default\d7;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\views_migration\Plugin\migrate\views\MigrateViewsHandlerPluginBase;

/**
 * The default Migrate Views Argument Default plugin.
 *
 * This plugin is used to prepare the Views `argument_default` display options
 * for migration when no other migrate plugin exists for the current
 * argument_default plugin.
 *
 * @MigrateViewsArgumentDefault(
 *   id = "d7_default",
 *   core = {7},
 * )
 */
class DefaultArgumentDefault extends MigrateViewsHandlerPluginBase {

  /**
   * {@inheritdoc}
   */
  public function alterHandlerConfig(array &$handler_config) {
    if (isset($handler_config['default_argument_type']) && !in_array($handler_config['default_argument_type'], $this->pluginList['argument_default'], TRUE)) {
      $message = sprintf("The '%s' default_argument plugin does not exist. Replaced with the 'fixed' plugin", $handler_config['default_argument_type']);
      $this->saveMessage($message, MigrationInterface::MESSAGE_WARNING);
      $handler_config['default_argument_type'] = 'fixed';
    }
    if (!isset($handler_config['default_argument_type']) || (isset($handler_config['default_argument_type']) && !is_array($handler_config['default_argument_options']))) {
      $handler_config['default_argument_options'] = ['default' => []];
    }
  }

}
