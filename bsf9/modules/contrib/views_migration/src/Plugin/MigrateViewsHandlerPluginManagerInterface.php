<?php

namespace Drupal\views_migration\Plugin;

use Drupal\migrate\Plugin\MigratePluginManagerInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\views_migration\Plugin\migrate\views\SourceHandlerInfoProvider;

/**
 * An interface implemented by "Migrate Views Handler" plugin managers.
 */
interface MigrateViewsHandlerPluginManagerInterface extends MigratePluginManagerInterface {

  /**
   * Creates a pre-configured instance of a migrate views handler plugin.
   *
   * A specific createInstance method is necessary to pass the Source Handler
   * Info Provider on.
   *
   * @param string $plugin_id
   *   The ID of the plugin being instantiated.
   * @param array $configuration
   *   An array of configuration relevant to the plugin instance.
   * @param \Drupal\migrate\Plugin\MigrationInterface|null $migration
   *   The migration context in which the plugin will run.
   * @param \Drupal\views_migration\Plugin\migrate\views\SourceHandlerInfoProvider|null $info_provider
   *   Provides information about the source handler being migrated.
   *
   * @return \Drupal\views_migration\Plugin\MigrateViewsHandlerInterface|\Drupal\views_migration\Plugin\MigrateViewsHandlerTableInterface|\Drupal\views_migration\Plugin\MigrateViewsHandlerAssociatedInterface|\Drupal\views_migration\Plugin\MigrateViewsHandlerTextFormatInterface
   *   A fully configured plugin instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException If the instance cannot be created, such as if the ID is invalid.
   */
  public function createInstance($plugin_id, array $configuration = [], MigrationInterface $migration = NULL, SourceHandlerInfoProvider $info_provider = NULL);

  /**
   * Gets the Migrate Views plugin for the provided source Views Handler.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration context in which the plugin will run.
   * @param \Drupal\views_migration\Plugin\migrate\views\SourceHandlerInfoProvider $info_provider
   *   Provides info about the current Views Handler being migrated.
   *
   * @return \Drupal\views_migration\Plugin\MigrateViewsHandlerInterface
   *   The Migrate Views Handler plugin for the provided source Views Handler.
   */
  public function getViewsHandlerMigratePlugin(MigrationInterface $migration, SourceHandlerInfoProvider $info_provider);

  /**
   * Gets the Migrate Views plugin for the provided source Handler Table.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration context in which the plugin will run.
   * @param string $table
   *   The source Views Handler Table value.
   * @param \Drupal\views_migration\Plugin\migrate\views\SourceHandlerInfoProvider $info_provider
   *   Provides info about the current Views Handler being migrated.
   *
   * @return \Drupal\views_migration\Plugin\MigrateViewsHandlerTableInterface
   *   The Migrate Views Handler Table plugin for the provided source Views
   *   Handler Table value.
   */
  public function getViewsHandlerTableMigratePlugin(MigrationInterface $migration, string $table, SourceHandlerInfoProvider $info_provider);

  /**
   * Gets the Handler Associated Views Migrate Plugin.
   *
   * There are a number of Views plugins that are directly associated with
   * Handlers. For example:
   *  - Argument Default Plugins on Argument Handlers
   *  - Argument Validator Plugins on Argument Handlers
   *  - Style Summary Plugins on Argument Handlers.
   *
   * These Views Migrate plugins enable the plugins settings to be modified for
   * the Handler they are associated with.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration context in which the plugin will run.
   * @param string $source_plugin_id
   *   The source Views Plugin ID.
   * @param \Drupal\views_migration\Plugin\migrate\views\SourceHandlerInfoProvider $info_provider
   *   Provides info about the current Views Handler being migrated.
   *
   * @return \Drupal\views_migration\Plugin\MigrateViewsHandlerAssociatedInterface
   *   The Handler Associated Views Migrate Plugin for the provided source
   *   Views Plugin ID.
   */
  public function getViewsHandlerAssociatedPlugin(MigrationInterface $migration, string $source_plugin_id, SourceHandlerInfoProvider $info_provider);

  /**
   * Gets the Migrate Views plugin for the provided source Text Format.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration context in which the plugin will run.
   * @param string $source_format
   *   The source Text Format.
   * @param \Drupal\views_migration\Plugin\migrate\views\SourceHandlerInfoProvider $info_provider
   *   Provides info about the current Views Handler being migrated.
   *
   * @return \Drupal\views_migration\Plugin\MigrateViewsHandlerTextFormatInterface
   *   The Migrate Views Handler Text Format plugin for the provided source
   *   Text format.
   */
  public function getViewsHandlerTextFormatPlugin(MigrationInterface $migration, string $source_format, SourceHandlerInfoProvider $info_provider);

}
