<?php

namespace Drupal\views_migration\Plugin\migrate\views\row\d7;

/**
 * The Migrate Views plugin for the "node_rss" Views Row Plugin.
 *
 * @MigrateViewsRow(
 *   id = "node_rss",
 *   plugin_ids = {
 *     "node_rss",
 *   },
 *   core = {7},
 * )
 */
class NodeRss extends DefaultRow {

  /**
   * {@inheritdoc}
   */
  public function prepareDisplayOptions(array &$display_options) {
    $rowOptions = $display_options['row_options'];
    $display_options['row'] = [
      'type' => $display_options['row_plugin'],
      'options' => [
        'relationship' => $rowOptions['relationship'],
        'view_mode' => $rowOptions['item_length'],
      ],
    ];
    unset($display_options['row_plugin'], $display_options['row_options']);
    parent::prepareDisplayOptions($display_options);
  }

}
