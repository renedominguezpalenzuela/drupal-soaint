<?php

namespace Drupal\views_migration\Plugin\migrate\views\argument\d7;

/**
 * The default Migrate Views Argument plugin for Entity Fields.
 *
 * This plugin is used to prepare the Views `argument` display options for
 * migration if:
 *  - The Handler Field represents an Entity Field.
 *  - The is no Migrate Views Argument plugin for the Entity Field's type.
 *
 * @MigrateViewsArgument(
 *   id = "d7_default_entity_field",
 *   core = {7},
 * )
 */
class DefaultEntityField extends DefaultArgument {

  /**
   * Override the parent to declare the correct var type.
   *
   * @var \Drupal\views_migration\Plugin\migrate\views\SourceHandlerEntityFieldInfoProvider
   */
  protected $infoProvider;

}
