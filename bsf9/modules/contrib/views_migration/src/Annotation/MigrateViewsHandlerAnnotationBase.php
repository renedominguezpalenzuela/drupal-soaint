<?php

namespace Drupal\views_migration\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * An abstract base class for all Migrate Views Handler plugin annotations.
 *
 * Views Handlers types include:
 *   - area (ie header, footer)
 *   - argument
 *   - field
 *   - filter
 *   - relationship
 *   - sort.
 */
abstract class MigrateViewsHandlerAnnotationBase extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The Drupal core version(s) this plugin applies to.
   *
   * @var int[]
   */
  public $core;

  /**
   * The Views Handler Table Field IDs the plugin is responsible for.
   *
   * This property can be combined with the $table property below to limit this
   * plugin to only be used for the fields listed when they are associated with
   * the declared $table.
   *
   * @var string[]
   *
   * @see \Drupal\views_migration\Plugin\MigrateViewsHandlerPluginManager::getViewsHandlerMigratePluginId()
   */
  public $field_ids = [];

  /**
   * Used in combination with $field_ids to limit fields to the specified table.
   *
   * @var string
   *
   * @see \Drupal\views_migration\Annotation\MigrateViewsHandlerAnnotationBase::$field_ids
   * @see \Drupal\views_migration\Plugin\MigrateViewsHandlerPluginManager::getViewsHandlerMigratePluginId()
   */
  public $table;

  /**
   * The Views Handler Field's Entity Field Types the plugin is responsible for.
   *
   * This is only applicable if the Views Handler Field represents an Entity
   * Field.
   *
   * @var string[]
   *
   * @see \Drupal\views_migration\Plugin\MigrateViewsHandlerPluginManager::getViewsHandlerMigratePluginId()
   */
  public $entity_field_types = [];

  /**
   * The Views Handler Tables the plugin is responsible for.
   *
   * @var string[]
   *
   * @see \Drupal\views_migration\Plugin\MigrateViewsHandlerPluginManager::getViewsHandlerMigratePluginId()
   */
  public $tables = [];

}
