<?php

namespace Drupal\views_migration\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Migrate Views Base Table plugin annotation object.
 *
 * Plugin Namespace: Plugin\migrate\views\base_table.
 *
 * @ingroup views_migration
 *
 * @Annotation
 */
class MigrateViewsBaseTable extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The Views Base Tables the plugin is responsible for.
   *
   * @var string[]
   */
  public $base_tables = [];

  /**
   * The Drupal core version(s) this plugin applies to.
   *
   * @var int[]
   */
  public $core;

}
