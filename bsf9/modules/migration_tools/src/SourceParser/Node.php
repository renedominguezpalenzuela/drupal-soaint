<?php

namespace Drupal\migration_tools\SourceParser;

use Drupal\migration_tools\Message;
use Drupal\migration_tools\Obtainer\Job;

/**
 * Class SourceParser\Node.
 *
 * Includes Node class, parses static HTML files via queryPath.
 *
 * @package migration_tools
 */
class Node extends HtmlBase {

  /**
   * {@inheritdoc}
   */
  public function __construct($file_id, $html, &$row) {
    parent::__construct($file_id, $html, $row);

  }

  /**
   * Validate basic requirements and alert if needed.
   */
  protected function validateParse() {
    // An empty title should throw an error.
    if (empty($this->row->getSourceProperty('title'))) {
      Message::make("The title for @fileid is empty.", ["@fileid" => $this->fileId], Message::ALERT);
    }

    // A body is not required, but should be cause for alarm.
    if (empty($this->row->getSourceProperty('body'))) {
      Message::make("The body for @fileid is empty.", ["@fileid" => $this->fileId], Message::ALERT);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function setDefaultObtainerJobs() {
    // Basic nodes will only have a title and a body.  Other SourceParsers can
    // extend this and additional Searches can be added in prepareRow.
    $title = new Job('title', 'ObtainTitle');
    $title->addSearch('pluckSelector', ["h1", 1]);
    $title->addSearch('pluckSelector', ["title", 1]);
    $this->addObtainerJob($title);

    $body = new Job('body', 'ObtainBody', TRUE);
    $body->addSearch('findTopBodyHtml');
    $this->addObtainerJob($body);
  }

}
