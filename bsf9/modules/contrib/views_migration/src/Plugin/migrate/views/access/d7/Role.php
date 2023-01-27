<?php

namespace Drupal\views_migration\Plugin\migrate\views\access\d7;

/**
 * The Migrate Views plugin for the "role" Views Access Plugin.
 *
 * @MigrateViewsAccess(
 *   id = "role",
 *   plugin_ids = {
 *     "role"
 *   },
 *   core = {7},
 * )
 */
class Role extends DefaultAccess {

  /**
   * {@inheritdoc}
   */
  public function prepareDisplayOptions(array &$display_options) {
    $role_approved = [];
    if (!is_array($display_options['access']['role'])) {
      parent::prepareDisplayOptions($display_options);
      return;
    }
    foreach ($display_options['access']['role'] as $key => $value) {
      $role_approved[$this->userRoles[$key]] = $this->userRoles[$key];
    }
    unset($display_options['access']['role']);
    $display_options['access']['options']['role'] = $role_approved;
    parent::prepareDisplayOptions($display_options);
  }

}
