<?php

namespace Drupal\views_migration\Plugin\migrate\views\handler_table\d7;

use Drupal\views_migration\Plugin\migrate\views\MigrateViewsPluginBase;
use Drupal\views_migration\Plugin\migrate\views\SourceHandlerEntityFieldInfoProvider;
use Drupal\views_migration\Plugin\migrate\views\SourceHandlerInfoProvider;
use Drupal\views_migration\Plugin\MigrateViewsHandlerTableInterface;

/**
 * The default Migrate Views Field plugin for Entity Fields.
 *
 * This plugin is used to provide the new Views Handler Table value when no
 * other migrate plugin exists for the source table value and the Field
 * represents an Entity Field.
 *
 * @MigrateViewsHandlerTable(
 *   id = "d7_default_entity_field",
 *   core = {7},
 * )
 */
class DefaultEntityField extends MigrateViewsPluginBase implements MigrateViewsHandlerTableInterface {

  /**
   * Override the parent to declare the correct var type.
   *
   * @var \Drupal\views_migration\Plugin\migrate\views\SourceHandlerEntityFieldInfoProvider
   */
  protected $infoProvider;

  /**
   * {@inheritdoc}
   *
   * @throws \LogicException
   *   If the Info Provider is not a SourceHandlerEntityFieldInfoProvider, which
   *   means the Field does not represent an Entity Field.
   */
  public function getNewTableValue(SourceHandlerInfoProvider $info_provider) {
    if (!is_a($info_provider, SourceHandlerEntityFieldInfoProvider::class)) {
      throw new \LogicException(sprintf("%s:%s: This plugin's info provider is not a SourceHandlerEntityFieldInfoProvider, which means the Views Field does not represent an Entity Field and this plugin should not be used.", __METHOD__, __LINE__));
    }
    return $info_provider->getFieldEntityType() . '__' . $info_provider->getEntityFieldName();
  }

}
