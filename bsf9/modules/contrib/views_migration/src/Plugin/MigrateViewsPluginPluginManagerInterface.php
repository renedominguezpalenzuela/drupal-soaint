<?php

namespace Drupal\views_migration\Plugin;

use Drupal\migrate\Plugin\MigratePluginManagerInterface;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * An interface implemented by "Migrate Views Plugin" plugin managers.
 */
interface MigrateViewsPluginPluginManagerInterface extends MigratePluginManagerInterface {

  /**
   * Gets the Migrate Views plugin for the provided source plugin id.
   *
   * If no $source_plugin_id is provided the default plugin is returned.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration context in which the plugin will run.
   * @param string|null $source_plugin_id
   *   The Views Plugin ID being migrated.
   *
   * @return \Drupal\views_migration\Plugin\MigrateViewsPluginInterface
   *   The plugin responsible for migrating the Views Plugin for the provided
   *   source plugin id.
   *
   * @see \Drupal\views_migration\Annotation\MigrateViewsAnnotationBase
   */
  public function getViewsPluginMigratePlugin(MigrationInterface $migration, ?string $source_plugin_id);

}
