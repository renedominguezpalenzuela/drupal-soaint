<?php

namespace Drupal\views_migration\Plugin\migrate\views\text_format\d7;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\views_migration\Plugin\migrate\views\MigrateViewsPluginBase;
use Drupal\views_migration\Plugin\MigrateViewsHandlerTextFormatInterface;

/**
 * The default Migrate Views Text Format plugin.
 *
 * This plugin is used to prepare the Views text format settings associated
 * with a Handler for migration when no other migrate plugin exists for the
 * current text format type.
 *
 * @MigrateViewsTextFormat(
 *   id = "d7_default",
 *   core = {7},
 * )
 */
class DefaultTextFormat extends MigrateViewsPluginBase implements MigrateViewsHandlerTextFormatInterface {

  /**
   * {@inheritdoc}
   */
  public function getNewTextFormat(string $source_format) {
    if (!array_key_exists($source_format, filter_formats())) {
      $message = sprintf("The text format '%s' does not exist. Replaced with the 'basic_html' format.", $source_format);
      $this->saveMessage($message, MigrationInterface::MESSAGE_WARNING);
      return 'basic_html';
    }
    return $source_format;
  }

}
