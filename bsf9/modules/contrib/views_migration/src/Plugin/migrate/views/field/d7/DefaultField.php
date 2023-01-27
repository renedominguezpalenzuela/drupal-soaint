<?php

namespace Drupal\views_migration\Plugin\migrate\views\field\d7;

use Drupal\Component\Utility\NestedArray;
use Drupal\views_migration\Plugin\migrate\views\MigrateViewsHandlerPluginBase;

/**
 * The default Migrate Views Field plugin.
 *
 * This plugin is used to prepare the Views `field` display options for
 * migration when no other migrate plugin exists for the current field plugin.
 *
 * @MigrateViewsField(
 *   id = "d7_default",
 *   core = {7},
 * )
 */
class DefaultField extends MigrateViewsHandlerPluginBase {

  /**
   * {@inheritdoc}
   */
  public function alterHandlerConfig(array &$handler_config) {
    $this->alterTokenizedSettings($handler_config);
    // Call the Migrate Table plugin to modify the table value.
    if (isset($handler_config['table'])) {
      $handler_config['table'] = $this->getViewsHandlerTableMigratePlugin($handler_config['table'])->getNewTableValue($this->infoProvider);
    }
    $this->alterFieldFormatterSettings($handler_config);
    $this->configurePluginId($handler_config, 'field');
  }

  /**
   * Fix the alter text settings.
   *
   * @param array $handler_config
   *   The Views Handler's configuration.
   */
  protected function alterTokenizedSettings(array &$handler_config) {
    $tokenized_keys = [
      'alter/text',
      'alter/path',
      'empty',
    ];
    foreach ($tokenized_keys as $key) {
      $option_value = NestedArray::getValue($handler_config, explode('/', $key), $key_exists);
      if ($key_exists) {
        $option_value = $this->fixTokenFormat($option_value);
        NestedArray::setValue($handler_config, explode('/', $key), $option_value, TRUE);
      }
    }
  }

  /**
   * Alter the Field Formatter type and settings.
   *
   * @param array $handler_config
   *   The Views Handler's configuration.
   */
  protected function alterFieldFormatterSettings(array &$handler_config) {
    if (array_key_exists('link_to_node', $handler_config)) {
      $handler_config['settings']['link_to_entity'] = $handler_config['link_to_node'];
      unset($handler_config['link_to_node']);
    }
    if (array_key_exists('date_format', $handler_config)) {
      $handler_config['settings']['date_format'] = $handler_config['date_format'];
      unset($handler_config['date_format']);
    }
    if (isset($handler_config['type'])) {
      $this->getViewsHandlerAssociatedPlugin('field_formatter', $handler_config['type'])->alterHandlerConfig($handler_config);
    }
  }

}
