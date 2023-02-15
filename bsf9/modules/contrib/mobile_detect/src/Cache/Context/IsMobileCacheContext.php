<?php

namespace Drupal\mobile_detect\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;
use Detection\MobileDetect;

/**
 * Defines the 'Is mobile' cache context.
 *
 * Cache context ID: 'mobile_detect_is_mobile'.
 */
class IsMobileCacheContext implements CacheContextInterface {

  /**
   * @var \Detection\MobileDetect
   */
  protected $mobileDetect;

  /**
   * Constructs an IsFrontPathCacheContext object.
   *
   * @param \Detection\MobileDetect $mobile_detect
   */
  public function __construct(MobileDetect $mobile_detect) {
    $this->mobileDetect = $mobile_detect;
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return 'Is mobile';
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    return (string) $this->mobileDetect->isMobile();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    return new CacheableMetadata();
  }

}
