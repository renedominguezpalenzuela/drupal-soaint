<?php

namespace Drupal\slick\Entity;

use Drupal\blazy\Blazy;

/**
 * Defines the Slick configuration entity.
 *
 * @ConfigEntityType(
 *   id = "slick",
 *   label = @Translation("Slick optionset"),
 *   list_path = "admin/config/media/slick",
 *   config_prefix = "optionset",
 *   entity_keys = {
 *     "id" = "name",
 *     "label" = "label",
 *     "status" = "status",
 *     "weight" = "weight",
 *   },
 *   config_export = {
 *     "id",
 *     "name",
 *     "weight",
 *     "label",
 *     "group",
 *     "skin",
 *     "breakpoints",
 *     "optimized",
 *     "options",
 *   }
 * )
 */
class Slick extends SlickBase implements SlickInterface {

  /**
   * The optionset group for easy selections.
   *
   * @var string
   */
  protected $group = '';

  /**
   * The skin name for the optionset.
   *
   * @var string
   */
  protected $skin = '';

  /**
   * The number of breakpoints for the optionset.
   *
   * @var int
   */
  protected $breakpoints = 0;

  /**
   * The flag indicating to optimize the stored options by removing defaults.
   *
   * @var bool
   */
  protected $optimized = FALSE;

  /**
   * {@inheritdoc}
   */
  public function getSkin() {
    return $this->skin;
  }

  /**
   * {@inheritdoc}
   */
  public function getBreakpoints() {
    return $this->breakpoints;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroup() {
    return $this->group;
  }

  /**
   * {@inheritdoc}
   */
  public function optimized() {
    return $this->optimized;
  }

  /**
   * Returns the Slick responsive settings.
   *
   * @return array
   *   The responsive options.
   */
  public function getResponsiveOptions() {
    if (empty($this->breakpoints)) {
      return FALSE;
    }
    $options = [];
    if (isset($this->options['responsives']['responsive'])) {
      $responsives = $this->options['responsives'];
      if ($responsives['responsive']) {
        foreach ($responsives['responsive'] as $delta => $responsive) {
          if (empty($responsives['responsive'][$delta]['breakpoint'])) {
            unset($responsives['responsive'][$delta]);
          }
          if (isset($responsives['responsive'][$delta])) {
            $options[$delta] = $responsive;
          }
        }
      }
    }
    return $options;
  }

  /**
   * Sets the Slick responsive settings.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setResponsiveSettings($values, $delta = 0, $key = 'settings') {
    $this->options['responsives']['responsive'][$delta][$key] = $values;
    return $this;
  }

  /**
   * Strip out options containing default values so to have real clean JSON.
   *
   * @return array
   *   The cleaned out settings.
   */
  public function removeDefaultValues(array $js) {
    $config   = [];
    $defaults = self::defaultSettings();

    // Remove wasted dependent options if disabled, empty or not.
    if (!$this->optimized) {
      $this->removeWastedDependentOptions($js);
    }

    $config = array_diff_assoc($js, $defaults);

    // Remove empty lazyLoad, or left to default ondemand, to avoid JS error.
    if (empty($config['lazyLoad'])) {
      unset($config['lazyLoad']);
    }

    // Do not pass arrows HTML to JSON object as some are enforced.
    $excludes = [
      'downArrow',
      'downArrowTarget',
      'downArrowOffset',
      'prevArrow',
      'nextArrow',
    ];
    foreach ($excludes as $key) {
      unset($config[$key]);
    }

    // Clean up responsive options if similar to defaults.
    if ($responsives = $this->getResponsiveOptions()) {
      $cleaned = [];
      foreach ($responsives as $key => $responsive) {
        $cleaned[$key]['breakpoint'] = $responsives[$key]['breakpoint'];

        // Destroy responsive slick if so configured.
        if (!empty($responsives[$key]['unslick'])) {
          $cleaned[$key]['settings'] = 'unslick';
          unset($responsives[$key]['unslick']);
        }
        else {
          // Remove wasted dependent options if disabled, empty or not.
          if (!$this->optimized) {
            $this->removeWastedDependentOptions($responsives[$key]['settings']);
          }
          $cleaned[$key]['settings'] = array_diff_assoc($responsives[$key]['settings'], $defaults);
        }
      }
      $config['responsive'] = $cleaned;
    }
    return $config;
  }

  /**
   * Removes wasted dependent options, even if not empty.
   */
  public function removeWastedDependentOptions(array &$js) {
    foreach (self::getDependentOptions() as $key => $option) {
      if (isset($js[$key]) && empty($js[$key])) {
        foreach ($option as $dependent) {
          unset($js[$dependent]);
        }
      }
    }

    if (!empty($js['useCSS']) && !empty($js['cssEaseBezier'])) {
      $js['cssEase'] = $js['cssEaseBezier'];
    }
    unset($js['cssEaseOverride'], $js['cssEaseBezier']);
  }

  /**
   * Defines the dependent options.
   *
   * @return array
   *   The dependent options.
   */
  public static function getDependentOptions() {
    $down_arrow = ['downArrowTarget', 'downArrowOffset'];
    return [
      'arrows'     => ['arrowsPlacement', 'prevArrow', 'nextArrow', 'downArrow'] + $down_arrow,
      'downArrow'  => $down_arrow,
      'autoplay'   => [
        'pauseOnHover',
        'pauseOnDotsHover',
        'pauseOnFocus',
        'autoplaySpeed',
        'useAutoplayToggleButton',
        'pauseIcon',
        'playIcon',
      ],
      'centerMode' => ['centerPadding'],
      'dots'       => ['dotsClass', 'appendDots'],
      'swipe'      => ['swipeToSlide'],
      'useCSS'     => ['cssEase', 'cssEaseBezier', 'cssEaseOverride'],
      'vertical'   => ['verticalSwiping'],
    ];
  }

  /**
   * Checks which lazyload to use.
   */
  public function whichLazy(array &$settings) {
    $lazy = $this->getSetting('lazyLoad');

    $settings['_lazy'] = TRUE;

    // @todo remove check post Blazy 2.10 to follow up Blazy improvements:
    // `Loading` priority, `No JavaScript: lazy`, etc.
    if (method_exists(Blazy::class, 'which')) {
      Blazy::which($settings, $lazy, 'lazy', 'lazy');
    }
    else {
      // @todo remove these post Blazy 2.10.
      $use_blazy = $lazy == 'blazy'
        || !empty($settings['blazy'])
        || !empty($settings['background'])
        || !empty($settings['responsive_image_style']);

      $lazy = $use_blazy ? 'blazy' : $lazy;

      // Allows Blazy to take over for advanced features like Responsive image,
      // CSS background, video, etc.
      if (!$use_blazy && $lazy) {
        $settings['lazy_class'] = $settings['lazy_attribute'] = 'lazy';
      }

      // Disable anything lazy-related settings if in preview mode.
      $settings['blazy'] = $use_blazy;
      $settings['lazy'] = empty($settings['is_preview']) ? $lazy : '';
    }
  }

  /**
   * If optionset does not exist, create one.
   */
  public static function verifyOptionset(array &$build, $name) {
    if (empty($build['optionset'])) {
      $build['optionset'] = self::loadWithFallback($name);
    }
    // Also returns it for convenient.
    return $build['optionset'];
  }

}
