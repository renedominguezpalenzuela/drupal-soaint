<?php

namespace Drupal\views_migration\Plugin\migrate\views\area\d7;

use Drupal\views_migration\Plugin\migrate\views\MigrateViewsHandlerPluginBase;

/**
 * The Migrate Views Area plugin for the Area Text Custom handler.
 *
 * @MigrateViewsArea(
 *   id = "area_text_custom",
 *   field_ids = {
 *     "area_text_custom",
 *   },
 *   core = {7},
 * )
 */
class AreaTextCustom extends MigrateViewsHandlerPluginBase {

  /**
   * {@inheritdoc}
   */
  public function alterHandlerConfig(array &$handler_config) {
    if (isset($handler_config['content'])) {
      $handler_config['content'] = $this->fixTokenFormat($handler_config['content']);
      $handler_config['content'] = $this->replaceArgumentTokens($handler_config['content']);
    }
  }

}
