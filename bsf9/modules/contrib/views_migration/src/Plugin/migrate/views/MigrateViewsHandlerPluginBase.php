<?php

namespace Drupal\views_migration\Plugin\migrate\views;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\views_migration\Plugin\MigrateViewsHandlerInterface;

/**
 * Provides a base for plugins responsible for migrating Views Handlers.
 */
abstract class MigrateViewsHandlerPluginBase extends MigrateViewsPluginBase implements MigrateViewsHandlerInterface {

  /**
   * Provides info about the Views Handler being migrated.
   *
   * @var \Drupal\views_migration\Plugin\migrate\views\SourceHandlerInfoProvider
   */
  protected $infoProvider;

  /**
   * Constructs a Migrate Views Handler plugin object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The Migration plugin.
   * @param \Drupal\views_migration\Plugin\migrate\views\SourceHandlerInfoProvider $info_provider
   *   Provides info about the current Views Handler being migrated.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, MigrationInterface $migration, SourceHandlerInfoProvider $info_provider) {
    $this->infoProvider = $info_provider;
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
  }

  /**
   * Gets the Views Migrate Handler Table Plugin for the provided source table.
   *
   * @param string $table
   *   The source Views Handler's Table value to get the plugin for,.
   *
   * @return \Drupal\views_migration\Plugin\MigrateViewsHandlerTableInterface
   *   The plugin responsible for determining the new Handler's table value.
   */
  protected function getViewsHandlerTableMigratePlugin(string $table) {
    $plugin_manager = $this->viewsMigration->getViewsHandlerMigratePluginManager('handler_table');
    return $plugin_manager->getViewsHandlerTableMigratePlugin($this->viewsMigration->getMigration(), $table, $this->infoProvider);
  }

  /**
   * Gets the Handler Associated Views Migrate Plugin for the provided plugin.
   *
   * There are a number of Views plugins that are directly associated with
   * Handlers. For example:
   *  - Argument Default Plugins on Argument Handlers
   *  - Argument Validator Plugins on Argument Handlers
   *  - Style Summary Plugins on Argument Handlers.
   *
   * The Views Migrate plugin returned enables the plugins settings to be
   * modified for the Handler they are associated with.
   *
   * @param string $plugin_type
   *   The type of Views Plugin to get.
   * @param string $plugin_id
   *   The Plugin ID to get.
   *
   * @return \Drupal\views_migration\Plugin\MigrateViewsHandlerAssociatedInterface
   *   The Handler Associated Views Migrate Plugin requested.
   */
  protected function getViewsHandlerAssociatedPlugin(string $plugin_type, string $plugin_id) {
    $plugin_manager = $this->viewsMigration->getViewsHandlerMigratePluginManager($plugin_type);
    return $plugin_manager->getViewsHandlerAssociatedPlugin($this->viewsMigration->getMigration(), $plugin_id, $this->infoProvider);
  }

  /**
   * Gets the Views Migrate Text Format Plugin for the provided source format.
   *
   * @param string $source_format
   *   The source Text Format.
   *
   * @return \Drupal\views_migration\Plugin\MigrateViewsHandlerTextFormatInterface
   *   The plugin responsible for determining the new Text Format.
   */
  protected function getViewsHandlerTextFormatMigratePlugin(string $source_format) {
    $plugin_manager = $this->viewsMigration->getViewsHandlerMigratePluginManager('text_format');
    return $plugin_manager->getViewsHandlerTextFormatPlugin($this->viewsMigration->getMigration(), $source_format, $this->infoProvider);
  }

  /**
   * Alters the Handler's Field value for fields that represent Entity IDs.
   *
   * Entity ID field, e.g. those that end in nid, uid, tid etc., need to be
   * changed to ending in '_target_id'.
   *
   * @param array $handler_config
   *   The Views Handler's configuration.
   */
  protected function alterEntityIdField(array &$handler_config) {
    if ($this->infoProvider->isEntityIdField($handler_config['field'])) {
      $handler_config['field_name'] = substr($handler_config['field'], 0, -4);
      $entityType = $this->infoProvider->getRelationshipEntityType($handler_config);
      $handler_config['entity_type'] = $entityType;
      $handler_config['field'] = substr($handler_config['field'], 0, -4) . '_target_id';
    }
    elseif ($this->infoProvider->isEntityReferenceField($handler_config)) {
      $handler_config['field_name'] = $handler_config['field'];
      $name_len = strlen($handler_config['field']);
      $entity_id_check = mb_substr($handler_config['field'], ($name_len - 4), 4);
      if ($entity_id_check == '_tid' || $entity_id_check == '_uid' || $entity_id_check == '_nid') {
        $field_name = mb_substr($handler_config['field'], 0, ($name_len - 4));
        if ($entity_id_check == '_tid' || $entity_id_check == '_uid') {
          $handler_config['field'] = $field_name . '_target_id';
        }
        else {
          $handler_config['field'] = $field_name;
        }
      }
    }
  }

  /**
   * Configures the Handler's plugin_id if it is not already set.
   *
   * @param array $handler_config
   *   The Views Handler's configuration.
   * @param string $handler_type
   *   The Handler type, e.g. filter, field etc.
   */
  protected function configurePluginId(array &$handler_config, string $handler_type) {
    if (!empty($handler_config['plugin_id'])) {
      return;
    }
    $table = $handler_config['table'];
    $field = $handler_config['field'];
    if (isset($this->viewsData[$table][$field][$handler_type]['id'])) {
      $handler_config['plugin_id'] = $this->viewsData[$table][$field][$handler_type]['id'];
    }
  }

  /**
   * Fix the token format.
   *
   * Change the token format from [token] to {{ token }}.
   *
   * @param string $string
   *   The string that may contain tokens.
   *
   * @return string
   *   The string with token format fixed.
   */
  protected function fixTokenFormat(string $string) {
    return str_replace(["[", "]"], [
      "{{ ",
      " }}",
    ], $string);
  }

  /**
   * Replace the argument tokens.
   *
   * Replace the old argument tokens i.e. %1 and !1 with the new twig syntax.
   *
   * @param string $string
   *   The string containing the argument tokens.
   *
   * @return string
   */
  protected function replaceArgumentTokens(string $string) {
    $arguments = $this->infoProvider->getDisplayOptions()['arguments'] ?? $this->defaultArguments ?? [];
    $count = 1;
    foreach ($arguments as $key => $info) {
      $string = str_replace(["%" . $count, "!" . $count], [
        "{{ arguments." . $key . " }}",
        "{{ raw_arguments." . $key . " }}",
      ], $string);
    }
    return $string;
  }

}
