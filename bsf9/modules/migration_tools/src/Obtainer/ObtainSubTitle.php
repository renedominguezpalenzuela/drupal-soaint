<?php

namespace Drupal\migration_tools\Obtainer;

/**
 * {@inheritdoc}
 */
class ObtainSubTitle extends ObtainTitle {

  /**
   * Overrides ObtainTitle truncator.
   */
  protected function truncateThisPossibleText() {
    // This should do nothing because no truncation is needed on subtitles.
  }

}
