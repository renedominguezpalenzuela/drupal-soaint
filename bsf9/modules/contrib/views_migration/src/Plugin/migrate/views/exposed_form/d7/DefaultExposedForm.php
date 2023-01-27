<?php

namespace Drupal\views_migration\Plugin\migrate\views\exposed_form\d7;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\views_migration\Plugin\migrate\views\MigrateViewsPluginPluginBase;

/**
 * The default Migrate Views Exposed Form plugin.
 *
 * This plugin is used to prepare the Views `exposed_form` display options for
 * migration when no other migrate plugin exists for the current exposed_form
 * plugin.
 *
 * @MigrateViewsExposedForm(
 *   id = "d7_default",
 *   core = {7},
 * )
 */
class DefaultExposedForm extends MigrateViewsPluginPluginBase {

  /**
   * {@inheritdoc}
   */
  public function prepareDisplayOptions(array &$display_options) {
    if (isset($display_options['exposed_form']['type']) && !in_array($display_options['exposed_form']['type'], $this->pluginList['exposed_form'], TRUE)) {
      $message = sprintf("The exposed_form plugin '%s' does not exist. Replaced with the 'basic' plugin.", $display_options['exposed_form']['type']);
      $this->saveMessage($message, MigrationInterface::MESSAGE_WARNING);
      $display_options['exposed_form'] = [
        'type' => 'basic',
      ];
    }
  }

}
