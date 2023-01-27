<?php

namespace Drupal\views_migration\Plugin\migrate\source;

use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Provides an interface for Views Migrate Source plugins.
 */
interface ViewsMigrationInterface {

  /**
   * Gets the Migration Plugin.
   *
   * @return \Drupal\migrate\Plugin\MigrationInterface
   */
  public function getMigration();

  /**
   * Gets the mapping of D6/7 User Role Ids to D8/9 User Role Machine Name.
   *
   * @return array
   */
  public function getUserRoles();

  /**
   * Gets the D8 Views Data.
   *
   * @return array
   */
  public function d8ViewsData();

  /**
   * Gets the Views Plugin List.
   *
   * @return array
   */
  public function getPluginList();

  /**
   * Gets the list of field formatters keyed by "all_formats" and "field_type".
   *
   * The "all_formats" key contains all field formatters. The "field_type" key
   * contains field formatters keyed by their field type.
   *
   * @return array
   */
  public function getFormatterList();

  /**
   * Gets the Entities base table array.
   *
   * @return array
   */
  public function baseTableArray();

  /**
   * Gets the Entity table array.
   *
   * @return array
   */
  public function entityTableArray();

  /**
   * Gets the relationships declared on the default display.
   *
   * @return array
   */
  public function getDefaultRelationships();

  /**
   * Gets the arguments declared on the default display.
   *
   * @return array
   */
  public function getDefaultArguments();

  /**
   * Returns the migrate views plugin manager for a views plugin type.
   *
   * @return \Drupal\views_migration\Plugin\MigrateViewsPluginPluginManager
   */
  public function getViewsPluginMigratePluginManager($type);

  /**
   * Returns the migrate views plugin manager for a views handler type.
   *
   * @return \Drupal\views_migration\Plugin\MigrateViewsHandlerPluginManager
   */
  public function getViewsHandlerMigratePluginManager($type);

  /**
   * Saves a message related to a source record in the migration message table.
   *
   * @param string $message
   *   The message to record.
   * @param int $level
   *   (optional) The message severity. Defaults to
   *   MigrationInterface::MESSAGE_ERROR.
   */
  public function saveMessage(string $message, int $level = MigrationInterface::MESSAGE_ERROR);

}
