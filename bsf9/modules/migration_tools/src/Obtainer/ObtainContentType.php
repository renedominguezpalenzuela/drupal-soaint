<?php

namespace Drupal\migration_tools\Obtainer;

/**
 * {@inheritdoc}
 *
 * Finders for content type are unique to other finders.  They MUST return
 * either the content type they are looking for OR ''.  They can not return
 * one content type or another as they will not cascade.
 */
class ObtainContentType extends Obtainer {

  /**
   * Find IMMEDIATE RELEASE for Press Release.
   */
  protected function findPrImmediateRelease() {
    $body = $this->queryPath->find('body')->first();
    $text = $body->text();
    $needle = 'IMMEDIATE RELEASE';
    return (stripos($text, $needle) !== FALSE) ? 'press_release' : '';
  }

}
