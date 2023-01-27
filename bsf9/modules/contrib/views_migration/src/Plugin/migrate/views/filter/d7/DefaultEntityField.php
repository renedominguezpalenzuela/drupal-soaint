<?php

namespace Drupal\views_migration\Plugin\migrate\views\filter\d7;

/**
 * The default Migrate Views Filter plugin for Entity Fields.
 *
 * This plugin is used to prepare the Views `filter` display options for
 * migration if:
 *  - The Handler Field represents an Entity Field.
 *  - The is no Migrate Views Filter plugin for the Entity Field's type.
 *
 * @MigrateViewsFilter(
 *   id = "d7_default_entity_field",
 *   core = {7},
 * )
 */
class DefaultEntityField extends DefaultFilter {

  /**
   * Override the parent to declare the correct var type.
   *
   * @var \Drupal\views_migration\Plugin\migrate\views\SourceHandlerEntityFieldInfoProvider
   */
  protected $infoProvider;

}
