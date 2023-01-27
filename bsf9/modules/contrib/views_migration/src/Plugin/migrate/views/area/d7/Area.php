<?php

namespace Drupal\views_migration\Plugin\migrate\views\area\d7;

use Drupal\views_migration\Plugin\migrate\views\MigrateViewsHandlerPluginBase;

/**
 * The Migrate Views Area plugin for the Area Text handler.
 *
 * @MigrateViewsArea(
 *   id = "area",
 *   field_ids = {
 *     "area",
 *   },
 *   core = {7},
 * )
 */
class Area extends MigrateViewsHandlerPluginBase {

  /**
   * {@inheritdoc}
   */
  public function alterHandlerConfig(array &$handler_config) {
    if (isset($handler_config['content'])) {
      $content_value = $this->fixTokenFormat($handler_config['content']);
      $content_value = $this->replaceArgumentTokens($content_value);
      $new_format = 'basic_html';
      if (isset($handler_config['format'])) {
        $new_format = $this->getViewsHandlerTextFormatMigratePlugin($handler_config['format'])->getNewTextFormat($handler_config['format']);
      }
      $handler_config['content'] = [
        'value' => $content_value,
        'format' => $new_format,
      ];
      unset($handler_config['format']);
    }
  }

}
