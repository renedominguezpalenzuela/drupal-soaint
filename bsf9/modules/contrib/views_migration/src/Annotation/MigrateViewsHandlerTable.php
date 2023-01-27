<?php

namespace Drupal\views_migration\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Migrate Views Handler Table plugin annotation object.
 *
 * Plugin Namespace: Plugin\migrate\views\handler_table.
 *
 * @ingroup views_migration
 *
 * @Annotation
 */
class MigrateViewsHandlerTable extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The Views Handler Tables the plugin is responsible for.
   *
   * @var string[]
   *
   * @see \Drupal\views_migration\Plugin\MigrateViewsHandlerPluginManager::getViewsHandlerTableMigratePluginId()
   */
  public $tables = [];

  /**
   * The Drupal core version(s) this plugin applies to.
   *
   * @var int[]
   */
  public $core;

}
