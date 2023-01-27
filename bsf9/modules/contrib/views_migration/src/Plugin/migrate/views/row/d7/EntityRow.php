<?php

namespace Drupal\views_migration\Plugin\migrate\views\row\d7;

/**
 * The Migrate Views plugin for entity Views Row Plugins.
 *
 * @MigrateViewsRow(
 *   id = "entity_row",
 *   plugin_ids = {
 *     "node",
 *     "user",
 *     "taxonomy_term",
 *     "file_managed"
 *   },
 *   core = {7},
 * )
 */
class EntityRow extends DefaultRow {

  /**
   * {@inheritdoc}
   */
  public function prepareDisplayOptions(array &$display_options) {
    $row_plugin_map = [
      'node' => 'entity:node',
      'users' => 'entity:user',
      'taxonomy_term' => 'entity:taxonomy_term',
      'file_managed' => 'entity:file',
    ];
    $display_options['row_plugin'] = $row_plugin_map[$display_options['row_plugin']];
    parent::prepareDisplayOptions($display_options);
  }

}
