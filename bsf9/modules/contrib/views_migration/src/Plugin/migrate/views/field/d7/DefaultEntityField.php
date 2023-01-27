<?php

namespace Drupal\views_migration\Plugin\migrate\views\field\d7;

/**
 * The default Migrate Views Field plugin for Entity Fields.
 *
 * This plugin is used to prepare the Views `field` display options for
 * migration if:
 *  - The Handler Field represents an Entity Field.
 *  - The is no Migrate Views Field plugin for the Entity Field's type.
 *
 * @MigrateViewsField(
 *   id = "d7_default_entity_field",
 *   core = {7},
 * )
 */
class DefaultEntityField extends DefaultField {

  /**
   * Override the parent to declare the correct var type.
   *
   * @var \Drupal\views_migration\Plugin\migrate\views\SourceHandlerEntityFieldInfoProvider
   */
  protected $infoProvider;

}
