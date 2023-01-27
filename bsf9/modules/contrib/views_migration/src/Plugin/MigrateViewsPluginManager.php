<?php

namespace Drupal\views_migration\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\migrate\Plugin\MigratePluginManager;
use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Component\DependencyInjection\Container;

/**
 * The base abstract class for all Migrate Views plugin managers.
 */
abstract class MigrateViewsPluginManager extends MigratePluginManager {

  public const DRUPAL_6 = 6;

  public const DRUPAL_7 = 7;

  /**
   * Constructs a MigrateViewsPluginManager object.
   *
   * @param string $type
   *   The plugin type, for example access.
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations,.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct($type, \Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    $plugin_definition_annotation_name = '\Drupal\views_migration\Annotation\MigrateViews' . Container::camelize($type);
    parent::__construct('views/' . $type, $namespaces, $cache_backend, $module_handler, $plugin_definition_annotation_name);
  }

  /**
   * Gets the Migrate Views plugin ID for the provided source plugin id.
   *
   * This method determines which Migrate Views plugin should be used for a
   * given source Views Plugin id as determined by the Migrate Views plugin's
   * plugin_ids annotation key.
   *
   * If no $source_plugin_id is provided the default plugin is returned.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration context in which the plugin will run.
   * @param string|null $source_plugin_id
   *   The Views Plugin ID being migrated.
   *
   * @return string
   *   The Migrate Views plugin ID for the provided source plugin id.
   *
   * @see \Drupal\views_migration\Annotation\MigrateViewsAnnotationBase
   */
  protected function getMigratePluginId(MigrationInterface $migration, ?string $source_plugin_id) {
    if (NULL === $source_plugin_id) {
      return $this->getDefaultPluginId($migration);
    }
    $definitions = $this->getDefinitions();
    $core = $this->getCoreVersion($migration);
    foreach ($definitions as $plugin_id => $definition) {
      if (!in_array($core, $definition['core'], TRUE)) {
        continue;
      }
      if (in_array($source_plugin_id, $definition['plugin_ids'], TRUE)) {
        return $plugin_id;
      }
    }
    return $this->getDefaultPluginId($migration);
  }

  /**
   * Finds the core version of a Drupal migration.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration.
   *
   * @return int
   *   The integer representation of the Drupal version.
   *
   * @throws \InvalidArgumentException
   */
  protected function getCoreVersion(MigrationInterface $migration) {
    $tags = $migration->getMigrationTags();
    if (in_array('Drupal 7', $tags, TRUE)) {
      return self::DRUPAL_7;
    }
    if (in_array('Drupal 6', $tags, TRUE)) {
      return self::DRUPAL_6;
    }
    throw new \InvalidArgumentException("Drupal Core version not found for this migration");
  }

  /**
   * Gets the default plugin id Prefix for the core version of the migration.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration.
   *
   * @return string
   *   The default plugin id Prefix for the core version of the migration.
   */
  protected function getCorePrefix(MigrationInterface $migration) {
    return 'd' . $this->getCoreVersion($migration) . '_';
  }

  /**
   * Gets the default plugin id to use when no other plugins can be found.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration.
   *
   * @return string
   *   The default plugin id to use when no other plugins can be found.
   */
  protected function getDefaultPluginId(MigrationInterface $migration) {
    return sprintf('%sdefault', $this->getCorePrefix($migration));
  }

  /**
   * Gets the default plugin id for handlers that represent entity fields.
   *
   * This is id is used when no other plugin is found and the handlers
   * represents an entity fields.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration.
   *
   * @return string
   *   The default plugin id for handlers that represent entity fields.
   */
  protected function getDefaultEntiyFieldPluginId(MigrationInterface $migration) {
    return sprintf('%sdefault_entity_field', $this->getCorePrefix($migration));
  }

}
