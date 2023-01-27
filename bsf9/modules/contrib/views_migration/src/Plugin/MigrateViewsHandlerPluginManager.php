<?php

namespace Drupal\views_migration\Plugin;

use Drupal\Component\Plugin\Factory\DefaultFactory;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\views_migration\Plugin\migrate\views\SourceHandlerEntityFieldInfoProvider;
use Drupal\views_migration\Plugin\migrate\views\SourceHandlerInfoProvider;

/**
 * Plugin manager for plugins responsible for migrating Views Handlers.
 */
class MigrateViewsHandlerPluginManager extends MigrateViewsPluginManager implements MigrateViewsHandlerPluginManagerInterface {

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = [], MigrationInterface $migration = NULL, SourceHandlerInfoProvider $info_provider = NULL) {
    $plugin_definition = $this->getDefinition($plugin_id);
    $plugin_class = DefaultFactory::getPluginClass($plugin_id, $plugin_definition);
    return new $plugin_class($configuration, $plugin_id, $plugin_definition, $migration, $info_provider);
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\views_migration\Plugin\MigrateViewsHandlerPluginManager::getViewsHandlerMigratePluginId()
   *   For more info on how the plugin is discovered.
   */
  public function getViewsHandlerMigratePlugin(MigrationInterface $migration, SourceHandlerInfoProvider $info_provider) {
    $migrate_plugin_id = $this->getViewsHandlerMigratePluginId($migration, $info_provider);
    return $this->createInstance($migrate_plugin_id, ['core' => $this->getCoreVersion($migration)], $migration, $info_provider);
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\views_migration\Plugin\MigrateViewsHandlerPluginManager::getViewsHandlerTableMigratePluginId()
   *   For more info on how the plugin is discovered.
   */
  public function getViewsHandlerTableMigratePlugin(MigrationInterface $migration, string $table, SourceHandlerInfoProvider $info_provider) {
    $migrate_plugin_id = $this->getViewsHandlerTableMigratePluginId($migration, $table);
    return $this->createInstance($migrate_plugin_id, ['core' => $this->getCoreVersion($migration)], $migration, $info_provider);
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\views_migration\Plugin\MigrateViewsHandlerPluginManager::getViewsHandlerAssociatedPluginId()
   *   For more info on how the plugin is discovered.
   */
  public function getViewsHandlerAssociatedPlugin(MigrationInterface $migration, string $source_plugin_id, SourceHandlerInfoProvider $info_provider) {
    $migrate_plugin_id = $this->getViewsHandlerAssociatedPluginId($migration, $source_plugin_id);
    return $this->createInstance($migrate_plugin_id, ['core' => $this->getCoreVersion($migration)], $migration, $info_provider);
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\views_migration\Plugin\MigrateViewsHandlerPluginManager::getViewsHandlerTextFormatPluginId()
   *   For more info on how the plugin is discovered.
   */
  public function getViewsHandlerTextFormatPlugin(MigrationInterface $migration, string $source_format, SourceHandlerInfoProvider $info_provider) {
    $migrate_plugin_id = $this->getViewsHandlerTextFormatPluginId($migration, $source_format);
    return $this->createInstance($migrate_plugin_id, ['core' => $this->getCoreVersion($migration)], $migration, $info_provider);
  }

  /**
   * Gets the Migrate Views plugin ID for the provided source Views Handler.
   *
   * This method determines which Migrate Views plugin should be used for a
   * given source Views Handler using its configuration values. It is
   * determined as follows:
   *   - If the plugin's $field_ids array contains the Handler's Field value,
   *       its ID is returned. If the plugin has defined a $table value, it must
   *       also match the Handler's Table value.
   *   - If no plugin is found above and the Field represents an Entity Field,
   *       and the plugin's $entity_field_types array contains the Entity Field
   *       type, its ID is returned.
   *   - If no plugin is found above and the plugin's $tables array contains
   *       the Handler's Table value, its ID is returned.
   *   - If no plugin is found above and the Field represents an Entity Field,
   *       the default_entity_field plugin for the Handler type is return if it
   *       exists.
   *   - Lastly if no other plugin is found, the default plugin for the Handler
   *       type is returned.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration context in which the plugin will run.
   * @param \Drupal\views_migration\Plugin\migrate\views\SourceHandlerInfoProvider $info_provider
   *   Provides info about the current Views Handler being migrated.
   *
   * @return string
   *   The Migrate Views Handler plugin ID for the provided source Views
   *   Handler.
   *
   * @see \Drupal\views_migration\Annotation\MigrateViewsAnnotationBase
   * @see \Drupal\views_migration\Plugin\MigrateViewsHandlerInterface
   */
  protected function getViewsHandlerMigratePluginId(MigrationInterface $migration, SourceHandlerInfoProvider $info_provider) {
    $definitions = $this->getDefinitions();
    $source_data = $info_provider->getSourceData();
    $core = $this->getCoreVersion($migration);
    // Determine if there is a specific plugin for the Handler's field value.
    foreach ($definitions as $plugin_id => $definition) {
      if (!in_array($core, $definition['core'], TRUE)) {
        continue;
      }
      if (in_array($source_data['field'], $definition['field_ids'], TRUE)) {
        // If the Annotation has declared a table, the source data table must
        // match.
        if (!empty($definition['table']) && $source_data['table'] !== $definition['table']) {
          continue;
        }
        return $plugin_id;
      }
    }
    // Determine if the Views Handler is for an Entity Field and call its field
    // type specific plugin.
    if (is_a($info_provider, SourceHandlerEntityFieldInfoProvider::class)) {
      $entity_field_type = $info_provider->getEntityFieldType();
      foreach ($definitions as $plugin_id => $definition) {
        if (!in_array($core, $definition['core'], TRUE)) {
          continue;
        }
        if (in_array($entity_field_type, $definition['entity_field_types'], TRUE)) {
          return $plugin_id;
        }
      }
    }
    // Determine if there is a specific plugin for the Handler's table value.
    foreach ($definitions as $plugin_id => $definition) {
      if (!in_array($core, $definition['core'], TRUE)) {
        continue;
      }
      if (in_array($source_data['table'], $definition['tables'], TRUE)) {
        return $plugin_id;
      }
    }
    // There are no specific field, entity field type or table plugins, return
    // the default plugins.
    if (is_a($info_provider, SourceHandlerEntityFieldInfoProvider::class) && array_key_exists($this->getDefaultEntiyFieldPluginId($migration), $definitions)) {
      return $this->getDefaultEntiyFieldPluginId($migration);
    }
    return $this->getDefaultPluginId($migration);
  }

  /**
   * Gets the Migrate Views plugin ID for the provided source Handler Table.
   *
   * This method determines which Migrate Views Handler Table plugin should be
   * used for a given source Views Handler table as follows:
   *   - If the plugin's $tables array contains the Handler's Table value, its
   *       ID is returned.
   *   - If no plugin is found above and the Field represents an Entity Field,
   *       the default_entity_field plugin is returned.
   *   - Lastly if no other plugin is found, the default plugin is returned.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration context in which the plugin will run.
   * @param string $table
   *   The source Views Handler Table value.
   *
   * @return string
   *   The Migrate Views Handler Table plugin ID for the provided source Views
   *   Handler Table value.
   *
   * @see \Drupal\views_migration\Annotation\MigrateViewsHandlerTable
   * @see \Drupal\views_migration\Plugin\MigrateViewsHandlerTableInterface
   * @see \Drupal\views_migration\Plugin\migrate\views\handler_table\d7\DefaultHandlerTable
   * @see \Drupal\views_migration\Plugin\migrate\views\handler_table\d7\DefaultEntityField
   */
  protected function getViewsHandlerTableMigratePluginId(MigrationInterface $migration, string $table) {
    $definitions = $this->getDefinitions();
    $core = $this->getCoreVersion($migration);
    foreach ($definitions as $plugin_id => $definition) {
      if (!in_array($core, $definition['core'], TRUE)) {
        continue;
      }
      if (in_array($table, $definition['tables'], TRUE)) {
        return $plugin_id;
      }
    }
    // Determine if the Views Handler is for an Entity Field and call its
    // specific plugin.
    if (SourceHandlerEntityFieldInfoProvider::isEntityField(['table' => $table]) && array_key_exists($this->getDefaultEntiyFieldPluginId($migration), $definitions)) {
      return $this->getDefaultEntiyFieldPluginId($migration);
    }
    return $this->getDefaultPluginId($migration);
  }

  /**
   * Gets the Handler Associated Views Migrate Plugin ID.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration context in which the plugin will run.
   * @param string $source_plugin_id
   *   The source Views Plugin ID.
   *
   * @return string
   *   The Handler Associated Views Migrate Plugin ID for the provided source
   *   Views Plugin ID.
   *
   * @see \Drupal\views_migration\Plugin\MigrateViewsHandlerAssociatedInterface
   */
  protected function getViewsHandlerAssociatedPluginId(MigrationInterface $migration, string $source_plugin_id) {
    return $this->getMigratePluginId($migration, $source_plugin_id);
  }

  /**
   * Gets the Migrate Views plugin ID for the provided source Text Format.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration context in which the plugin will run.
   * @param string $source_format
   *   The source Text Format.
   *
   * @return string
   *   The Migrate Views Handler Text Format plugin ID for the provided source
   *   Text format.
   *
   * @see \Drupal\views_migration\Plugin\MigrateViewsHandlerTextFormatInterface
   */
  protected function getViewsHandlerTextFormatPluginId(MigrationInterface $migration, string $source_format) {
    $definitions = $this->getDefinitions();
    $core = $this->getCoreVersion($migration);
    foreach ($definitions as $plugin_id => $definition) {
      if (!in_array($core, $definition['core'], TRUE)) {
        continue;
      }
      if (in_array($source_format, $definition['formats'], TRUE)) {
        return $plugin_id;
      }
    }
    return $this->getDefaultPluginId($migration);
  }

}
