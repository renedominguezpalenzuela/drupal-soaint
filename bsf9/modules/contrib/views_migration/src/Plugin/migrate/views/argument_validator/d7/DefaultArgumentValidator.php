<?php

namespace Drupal\views_migration\Plugin\migrate\views\argument_validator\d7;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\views_migration\Plugin\migrate\views\MigrateViewsHandlerPluginBase;

/**
 * The default Migrate Views Argument Validator plugin.
 *
 * This plugin is used to prepare the Views `argument_validator` display options
 * for migration when no other migrate plugin exists for the current
 * argument_validator plugin.
 *
 * @MigrateViewsArgumentValidator(
 *   id = "d7_default",
 *   core = {7},
 * )
 */
class DefaultArgumentValidator extends MigrateViewsHandlerPluginBase {

  /**
   * {@inheritdoc}
   */
  public function alterHandlerConfig(array &$handler_config) {
    if (isset($handler_config['validate_options']['types'])) {
      $handler_config['validate_options']['bundles'] = $handler_config['validate_options']['types'];
      unset($handler_config['validate_options']['types']);
    }
    if (isset($handler_config['validate']['type']) && !in_array($handler_config['validate']['type'], $this->pluginList['argument_validator'], TRUE)) {
      // Modify entity validators.
      $type = 'entity:' . $handler_config['validate']['type'];
      if (in_array($type, $this->pluginList['argument_validator'], TRUE)) {
        $handler_config['validate']['type'] = $type;
        return;
      }
      // The argument_validator is unknown, replace with the "none" plugin.
      $message = sprintf("The '%s' default_validator plugin does not exist. Replaced with the 'none' plugin", $handler_config['validate']['type']);
      $this->saveMessage($message, MigrationInterface::MESSAGE_WARNING);
      $handler_config['validate']['type'] = 'none';
    }
  }

}
