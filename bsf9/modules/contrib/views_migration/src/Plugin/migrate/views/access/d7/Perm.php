<?php

namespace Drupal\views_migration\Plugin\migrate\views\access\d7;

/**
 * The Migrate Views plugin for the "perm" Views Access Plugin.
 *
 * @MigrateViewsAccess(
 *   id = "perm",
 *   plugin_ids = {
 *     "perm"
 *   },
 *   core = {7},
 * )
 */
class Perm extends DefaultAccess {

  /**
   * {@inheritdoc}
   */
  public function prepareDisplayOptions(array &$display_options) {
    $permissions_map = [
      'use PHP for block visibility' => 'use PHP for settings',
      'administer site-wide contact form' => 'administer contact forms',
      'post comments without approval' => 'skip comment approval',
      'edit own blog entries' => 'edit own blog content',
      'edit any blog entry' => 'edit any blog content',
      'delete own blog entries' => 'delete own blog content',
      'delete any blog entry' => 'delete any blog content',
      'create forum topics' => 'create forum content',
      'delete any forum topic' => 'delete any forum content',
      'delete own forum topics' => 'delete own forum content',
      'edit any forum topic' => 'edit any forum content',
      'edit own forum topics' => 'edit own forum content',
    ];
    if (isset($display_options['access'], $display_options['access']['perm'])) {
      $perm = $display_options['access']['perm'];
      $perm = $permissions_map[$perm] ?? $perm;
      if (is_null($perm)) {
        $perm = 'access content';
      }
      $display_options['access']['options']['perm'] = $perm;
    }
    parent::prepareDisplayOptions($display_options);
  }

}
