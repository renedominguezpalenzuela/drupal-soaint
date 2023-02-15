<?php

namespace Drupal\mobile_detect\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Mobile Detect' status block for dev purposes.
 *
 * @Block(
 *   id = "mobile_detect_status_block",
 *   admin_label = @Translation("Mobile Detect Status")
 * )
 */
class MobileDetectStatusBlock extends BlockBase {

  /**
   * {@inheritdoc}
   * 
   */
  public function build() {
    $renderable = [
      '#theme' => 'mobile_detect_status_block',
      '#internal_cache' => $this->internalCacheStatus()
    ];

    return $renderable;
  }
  
  /*
   * Returns true if "Internal Page Cache" module is enabled.
   * 
   */
  private function internalCacheStatus() {
    $enabled = false;
    $moduleHandler = \Drupal::service('module_handler');
    if ($moduleHandler->moduleExists('cache_page')) {
      $enabled = true;
    }
    return $enabled;
  }

}