<?php

namespace Drupal\slick;

use Drupal\slick\Entity\Slick;
use Drupal\blazy\BlazyFormatter;

/**
 * Provides Slick field formatters utilities.
 */
class SlickFormatter extends BlazyFormatter implements SlickFormatterInterface {

  /**
   * {@inheritdoc}
   */
  public function buildSettings(array &$build, $items) {
    $settings = &$build['settings'];
    $settings += SlickDefault::htmlSettings();

    // Prepare integration with Blazy.
    $settings['_unload'] = FALSE;

    // @todo move it into self::preSettingsData() post Blazy 2.10.
    $optionset = Slick::verifyOptionset($build, $settings['optionset']);

    // Only display thumbnail nav if having at least 2 slides. This might be
    // an issue such as for ElevateZoom Plus module, but it should work it out.
    $nav = $settings['nav'] ?? !empty($settings['optionset_thumbnail']) && isset($items[1]);

    // Do not bother for SlickTextFormatter, or when vanilla is on.
    if (empty($settings['vanilla'])) {
      $optionset->whichLazy($settings);
    }
    else {
      // Nothing to work with Vanilla on, disable the asnavfor, else JS error.
      $nav = FALSE;
    }

    $settings['nav'] = $nav;
    $blazies = $settings['blazies'] ?? NULL;
    if ($blazies) {
      $blazies->set('initial', $optionset->getSetting('initialSlide'))
        ->set('is.nav', $nav);
    }

    // Pass basic info to parent::buildSettings().
    parent::buildSettings($build, $items);
  }

  /**
   * {@inheritdoc}
   */
  public function preBuildElements(array &$build, $items, array $entities = []) {
    parent::preBuildElements($build, $items, $entities);

    $settings = &$build['settings'];

    // Only trim overridables options if disabled.
    if (empty($settings['override']) && isset($settings['overridables'])) {
      $settings['overridables'] = array_filter($settings['overridables']);
    }

    $this->getModuleHandler()->alter('slick_settings', $build, $items);
  }

}
