<?php

namespace Drupal\views_migration\Plugin\migrate\views\display\d7;

/**
 * The Migrate Views plugin for the "views_data_export" Views Display Plugin.
 *
 * @MigrateViewsDisplay(
 *   id = "views_data_export",
 *   plugin_ids = {
 *     "views_data_export"
 *   },
 *   core = {7},
 * )
 */
class ViewsDataExport extends DefaultDisplay {

  /**
   * {@inheritdoc}
   */
  public function prepareDisplayOptions(array &$display_options) {
    $display_options['display_plugin'] = 'data_export';
  }

}
