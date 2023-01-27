<?php

namespace Drupal\views_migration\Plugin;

use Drupal\migrate\Plugin\MigratePluginManagerInterface;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * An interface implemented by "Migrate Views Base Table" plugin managers.
 */
interface MigrateViewsBaseTablePluginManagerInterface extends MigratePluginManagerInterface {

  /**
   * Gets the Migrate Views plugin for the provided source base table.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration context in which the plugin will run.
   * @param string $source_base_table
   *   The base table of the view being migrated.
   *
   * @return \Drupal\views_migration\Plugin\MigrateViewsBaseTableInterface
   *   The plugin responsible for migrating the base table settings for the
   *   provided source base table.
   *
   * @see \Drupal\views_migration\Annotation\MigrateViewsBaseTable
   */
  public function getViewsBaseTableMigratePlugin(MigrationInterface $migration, string $source_base_table);

}
