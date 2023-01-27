<?php

namespace Drupal\views_migration\Plugin\migrate\views\argument\d7;

/**
 * The Views Migrate plugin for "user_roles" Argument Handlers.
 *
 * @MigrateViewsArgument(
 *   id = "users_roles",
 *   tables = {
 *     "users_roles"
 *   },
 *   core = {7},
 * )
 */
class UserRoles extends DefaultArgument {

  /**
   * {@inheritdoc}
   */
  public function alterHandlerConfig(array &$handler_config) {
    if (isset($handler_config['value'])) {
      $role_approved = [];
      foreach ($handler_config['value'] as $rid => $role_data) {
        $role_approved[$this->userRoles[$rid]] = $this->userRoles[$rid];
      }
      $handler_config['value'] = $role_approved;
    }
    $handler_config['plugin_id'] = 'user_roles';
    $handler_config['entity_type'] = 'user';
    $handler_config['entity_field'] = 'roles';
    $handler_config['table'] = 'user__roles';
    $handler_config['field'] = 'roles_target_id';
    $this->alterArgumentDefaultSettings($handler_config);
    $this->alterArgumentValidateSettings($handler_config);
    $this->alterSummarySettings($handler_config);
  }

}
