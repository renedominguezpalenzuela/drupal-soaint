<?php

namespace Drupal\views_migration\Plugin\migrate\views\argument\d7;

use Drupal\views_migration\Plugin\migrate\views\MigrateViewsHandlerPluginBase;

/**
 * The default Migrate Views Argument plugin.
 *
 * This plugin is used to prepare the Views `argument` display options for
 * migration when no other migrate plugin exists for the current argument plugin.
 *
 * @MigrateViewsArgument(
 *   id = "d7_default",
 *   core = {7},
 * )
 */
class DefaultArgument extends MigrateViewsHandlerPluginBase {

  /**
   * {@inheritdoc}
   */
  public function alterHandlerConfig(array &$handler_config) {
    // Call the Migrate Table plugin to modify the table value.
    if (isset($handler_config['table'])) {
      $handler_config['table'] = $this->getViewsHandlerTableMigratePlugin($handler_config['table'])->getNewTableValue($this->infoProvider);
    }
    $this->alterEntityIdField($handler_config);
    $this->alterArgumentDefaultSettings($handler_config);
    $this->alterArgumentValidateSettings($handler_config);
    $this->alterSummarySettings($handler_config);
    $this->configurePluginId($handler_config, 'argument');
  }

  /**
   * Alter the Argument Default Plugin and settings.
   *
   * @param array $handler_config
   *   The Views Handler's configuration.
   */
  protected function alterArgumentDefaultSettings(array &$handler_config) {
    if (isset($handler_config['default_argument_type'])) {
      $this->getViewsHandlerAssociatedPlugin('argument_default', $handler_config['default_argument_type'])->alterHandlerConfig($handler_config);
    }
  }

  /**
   * Alter the Argument Validator Plugin and settings.
   *
   * @param array $handler_config
   *   The Views Handler's configuration.
   */
  protected function alterArgumentValidateSettings(array &$handler_config) {
    if (isset($handler_config['validate']['type'])) {
      $this->getViewsHandlerAssociatedPlugin('argument_validator', $handler_config['validate']['type'])->alterHandlerConfig($handler_config);
    }
  }

  /**
   * Alter the Summary Plugin and settings.
   *
   * @param array $handler_config
   *   The Views Handler's configuration.
   */
  protected function alterSummarySettings(array &$handler_config) {
    if (isset($handler_config['summary']['format'])) {
      $this->getViewsHandlerAssociatedPlugin('style_summary', $handler_config['summary']['format'])->alterHandlerConfig($handler_config);
    }
  }

}
