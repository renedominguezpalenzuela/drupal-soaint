<?php

namespace Drupal\views_migration\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Migrate Views Text Format plugin annotation object.
 *
 * Plugin Namespace: Plugin\migrate\views\text_format.
 *
 * @ingroup views_migration
 *
 * @Annotation
 */
class MigrateViewsTextFormat extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The Text Formats the plugin is responsible for.
   *
   * @var string[]
   *
   * @see \Drupal\views_migration\Plugin\MigrateViewsHandlerPluginManager::getViewsHandlerTextFormatPluginId()
   */
  public $formats = [];

  /**
   * The Drupal core version(s) this plugin applies to.
   *
   * @var int[]
   */
  public $core;

}
