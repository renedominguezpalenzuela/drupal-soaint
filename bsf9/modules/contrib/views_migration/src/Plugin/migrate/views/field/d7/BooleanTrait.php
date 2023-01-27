<?php

namespace Drupal\views_migration\Plugin\migrate\views\field\d7;

/**
 * Provides functionality common to Views Fields using the boolean plugin.
 */
trait BooleanTrait {

  /**
   * Apply the configuration changes required to the Views Field.
   */
  public function alterHandlerConfigBoolean(array &$handler_config) {
    if (empty($handler_config['type'])) {
      $handler_config['type'] = 'yes-no';
    }
    $handler_config['settings']['format'] = $handler_config['type'];
    if (isset($handler_config['type_custom_true'])) {
      $handler_config['settings']['format_custom_true'] = $handler_config['type_custom_true'];
    }
    if (isset($handler_config['type_custom_false'])) {
      $handler_config['settings']['format_custom_false'] = $handler_config['type_custom_false'];
    }
    $handler_config['type'] = 'boolean';
  }

}
