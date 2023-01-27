<?php

namespace Drupal\views_migration\Plugin\migrate\views\base_table\d7;

/**
 * The Migrate Views plugin for the "commerce_product" base table.
 *
 * @MigrateViewsBaseTable(
 *   id = "commerce_product",
 *   core = {7},
 *   base_tables = {
 *     "commerce_product",
 *   }
 * )
 */
class CommerceProduct extends DefaultBaseTable {

  /**
   * {@inheritdoc}
   */
  public function getNewBaseTable(string $source_base_table) {
    return 'commerce_product_variation';
  }

}
