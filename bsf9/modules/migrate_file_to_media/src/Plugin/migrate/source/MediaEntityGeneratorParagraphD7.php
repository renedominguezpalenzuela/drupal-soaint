<?php

namespace Drupal\adcogov_migrate\Plugin\migrate\source\d7;

use Drupal\Component\Utility\UrlHelper;
use Drupal\paragraphs\Plugin\migrate\source\d7\ParagraphsItem;

/**
 * Migrate source for paragraph file fields.
 *
 * @MigrateSource(
 *   id = "media_entity_generator_d7_paragraph",
 *   source_module = "paragraphs",
 * )
 */
class MediaEntityGeneratorParagraphD7 extends ParagraphsItem {

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'target_id' => $this->t('The file entity ID.'),
      'file_id' => $this->t('The file entity ID.'),
      'file_path' => $this->t('The file path.'),
      'file_name' => $this->t('The file name.'),
      'file_alt' => $this->t('The file arl.'),
      'file_title' => $this->t('The file title.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'target_id' => [
        'type' => 'integer',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function count($refresh = FALSE) {
    return $this->initializeIterator()->count();
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator() {
    $query_files = $this->select('file_managed', 'f')
      ->fields('f')
      ->condition('uri', 'temporary://%', 'NOT LIKE')
      ->orderBy('f.timestamp');

    $all_files = $query_files->execute()->fetchAllAssoc('fid');

    $files_found = [];
    foreach ($this->configuration['field_names'] as $source_field) {
      $parent_iterator = parent::initializeIterator();
      foreach ($parent_iterator as $entity) {
        $item_id = $entity['item_id'];
        $revision_id = $entity['revision_id'];
        $field_value = $this->getFieldValues('paragraphs_item', $source_field, $item_id, $revision_id);
        foreach ($field_value as $reference) {
          // Support remote file urls.
          $file_url = $all_files[$reference['fid']]['uri'];
          if (!empty($this->configuration['d7_file_url'])) {
            $file_url = str_replace('public://', '', $file_url);
            $file_path = UrlHelper::encodePath($file_url);
            $file_url = $this->configuration['d7_file_url'] . $file_path;
          }
          if (!empty($all_files[$reference['fid']]['uri'])) {
            $files_found[] = $entity + [
              'target_id' => $reference['fid'],
              'alt' => $reference['alt'] ?? NULL,
              'title' => $reference['title'] ?? NULL,
              'display' => $reference['display'] ?? NULL,
              'description' => $reference['description'] ?? NULL,
              'langcode' => $this->configuration['langcode'],
              'file_name' => $all_files[$reference['fid']]['filename'],
              'file_path' => $file_url,
            ];
          }
        }
      }
    }
    return new \ArrayIterator($files_found);
  }

}
