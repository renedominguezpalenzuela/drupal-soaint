<?php

namespace Drupal\views_migration\Plugin;

use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Plugin manager for plugins responsible for migrating Views Plugins.
 */
class MigrateViewsBaseTablePluginManager extends MigrateViewsPluginManager implements MigrateViewsBaseTablePluginManagerInterface {

  /**
   * {@inheritdoc}
   */
  public function getViewsBaseTableMigratePlugin(MigrationInterface $migration, string $source_base_table) {
    $migrate_plugin_id = $this->getViewsBaseTableMigratePluginId($migration, $source_base_table);
    return $this->createInstance($migrate_plugin_id, ['core' => $this->getCoreVersion($migration)], $migration);
  }

  /**
   * Gets the Migrate Views plugin ID for the provided source base table.
   *
   * This method determines which Migrate Views plugin should be used for a
   * given source base table as determined by the Migrate Views plugin's
   * base_tables annotation key.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration context in which the plugin will run.
   * @param string $source_base_table
   *   The base table of the view being migrated.
   *
   * @return string
   *   The Migrate Views plugin ID for the provided source base table.
   *
   * @see \Drupal\views_migration\Annotation\MigrateViewsBaseTable
   */
  protected function getViewsBaseTableMigratePluginId(MigrationInterface $migration, string $source_base_table) {
    $definitions = $this->getDefinitions();
    $core = $this->getCoreVersion($migration);
    foreach ($definitions as $plugin_id => $definition) {
      if (!in_array($core, $definition['core'], TRUE)) {
        continue;
      }
      if (in_array($source_base_table, $definition['base_tables'], TRUE)) {
        return $plugin_id;
      }
    }
    return $this->getDefaultPluginId($migration);
  }

}
