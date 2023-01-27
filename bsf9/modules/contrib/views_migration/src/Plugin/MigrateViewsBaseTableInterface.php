<?php

namespace Drupal\views_migration\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Provides an interface for plugins that migrate a View's base table settings.
 */
interface MigrateViewsBaseTableInterface extends PluginInspectionInterface {

  /**
   * Provides the new Base Table for the View based on the source base table.
   *
   * @param string $source_base_table
   *   The base table of the view being migrated.
   */
  public function getNewBaseTable(string $source_base_table);

  /**
   * Gets the value to set the source "base_table" to.
   *
   * @param string $source_base_table
   *   The base table of the view being migrated.
   *
   * @return string
   */
  public function getNewSourceBaseTable(string $source_base_table);

  /**
   * Gets the value to set the source "base_field" to.
   *
   * @param string $source_base_table
   *   The base table of the view being migrated.
   *
   * @return string
   */
  public function getNewSourceBaseField(string $source_base_table);

  /**
   * Gets the base table's entity type.
   *
   * @param string $source_base_table
   *   The base table of the view being migrated.
   *
   * @return string
   */
  public function getBaseTableEntityType(string $source_base_table);

}
