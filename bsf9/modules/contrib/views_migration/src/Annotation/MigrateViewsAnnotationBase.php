<?php

namespace Drupal\views_migration\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an abstract base class for all Migrate Views plugin annotations.
 */
abstract class MigrateViewsAnnotationBase extends Plugin {

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
   * The Views Plugin IDs the plugin is responsible for.
   *
   * @var string[]
   */
  public $plugin_ids = [];

}
