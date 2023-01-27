<?php

namespace Drupal\views_migration\Plugin\migrate\views\field_formatter\d7;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\views_migration\Plugin\migrate\views\MigrateViewsHandlerPluginBase;
use Drupal\views_migration\Plugin\migrate\views\SourceHandlerEntityFieldInfoProvider;

/**
 * The default Migrate Views Field Formatter plugin.
 *
 * This plugin is used to prepare the Views Field's formatter display options
 * for migration when no other migrate plugin exists for the current
 * field formatter type.
 *
 * @MigrateViewsFieldFormatter(
 *   id = "d7_default",
 *   core = {7},
 * )
 */
class DefaultFieldFormatter extends MigrateViewsHandlerPluginBase {

  /**
   * {@inheritdoc}
   */
  public function alterHandlerConfig(array &$handler_config) {
    if (empty($handler_config['type'])) {
      return;
    }
    // If the formatter exists, there is nothing more to do.
    if (in_array($handler_config['type'], $this->formatterList['all_formats'], TRUE)) {
      return;
    }
    // If the current field represents an entity field we can attempt to get
    // the default formatter for the field type.
    if (!is_a($this->infoProvider, SourceHandlerEntityFieldInfoProvider::class)) {
      $this->removeLogMissingFormatter($handler_config);
      return;
    }
    /** @var \Drupal\Core\Field\FieldTypePluginManager $field_type_plugin_manager */
    $field_type_plugin_manager = \Drupal::service('plugin.manager.field.field_type');
    $field_config = $this->infoProvider->getFieldConfig($this->infoProvider->getFieldEntityType(), $this->infoProvider->getEntityFieldName());
    if (NULL === $field_config) {
      $this->removeLogMissingFormatter($handler_config);
      return;
    }
    $field_type = $this->infoProvider->getEntityFieldType();
    $settings = $field_config->get('settings');
    $formatters = $this->formatterList['field_type'][$field_type];
    $default_formatter = $field_type_plugin_manager->getDefinition($field_type)['default_formatter'] ?? NULL;
    if (NULL === $default_formatter) {
      $this->removeLogMissingFormatter($handler_config);
      return;
    }
    if (!in_array($default_formatter, $formatters, TRUE)) {
      $this->removeLogMissingFormatter($handler_config);
      return;
    }
    $message = sprintf("The '%s' field formatter plugin for field %s does not exist. It has been replaced by the default formatter for the field type '%s'.", $handler_config['type'], $this->infoProvider->getEntityFieldName(), $default_formatter);
    $this->saveMessage($message, MigrationInterface::MESSAGE_WARNING);
    $handler_config['type'] = $default_formatter;
    $handler_config['settings'] = $settings;
  }

  /**
   * Remove and log the Formatter that doesn't exist in Drupal 8/9.
   *
   * @param array $handler_config
   *   The Views Handler Config to alter.
   */
  protected function removeLogMissingFormatter(array &$handler_config) {
    $message = sprintf("The '%s' field formatter plugin for field %s does not exist. It has been removed as no replacement could be determined.", $handler_config['type'], $handler_config['field']);
    $this->saveMessage($message, MigrationInterface::MESSAGE_WARNING);
    unset($handler_config['type']);
  }

}
