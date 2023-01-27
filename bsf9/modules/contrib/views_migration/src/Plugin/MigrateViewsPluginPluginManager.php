<?php

namespace Drupal\views_migration\Plugin;

use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Plugin manager for plugins responsible for migrating Views Plugins.
 */
class MigrateViewsPluginPluginManager extends MigrateViewsPluginManager implements MigrateViewsPluginPluginManagerInterface {

  /**
   * {@inheritdoc}
   */
  public function getViewsPluginMigratePlugin(MigrationInterface $migration, ?string $source_plugin_id) {
    $migrate_plugin_id = $this->getMigratePluginId($migration, $source_plugin_id);
    return $this->createInstance($migrate_plugin_id, ['core' => $this->getCoreVersion($migration)], $migration);
  }

}
