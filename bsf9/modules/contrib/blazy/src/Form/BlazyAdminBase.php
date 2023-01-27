<?php

namespace Drupal\blazy\Form;

use Drupal\Core\Url;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\BlazyManagerInterface;
use Drupal\blazy\Utility\Path;

/**
 * A base for blazy admin integration to have re-usable methods in one place.
 *
 * @see \Drupal\gridstack\Form\GridStackAdmin
 * @see \Drupal\mason\Form\MasonAdmin
 * @see \Drupal\slick\Form\SlickAdmin
 * @see \Drupal\blazy\Form\BlazyAdminFormatterBase
 */
abstract class BlazyAdminBase implements BlazyAdminInterface {

  use StringTranslationTrait;

  /**
   * A state that represents the responsive image style is disabled.
   */
  const STATE_RESPONSIVE_IMAGE_STYLE_DISABLED = 0;

  /**
   * A state that represents the media switch lightbox is enabled.
   */
  const STATE_LIGHTBOX_ENABLED = 1;

  /**
   * A state that represents the media switch iframe is enabled.
   */
  const STATE_IFRAME_ENABLED = 2;

  /**
   * A state that represents the thumbnail style is enabled.
   */
  const STATE_THUMBNAIL_STYLE_ENABLED = 3;

  /**
   * A state that represents the custom lightbox caption is enabled.
   */
  const STATE_LIGHTBOX_CUSTOM = 4;

  /**
   * A state that represents the image rendered switch is enabled.
   */
  const STATE_IMAGE_RENDERED_ENABLED = 5;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The typed config manager service.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface
   */
  protected $typedConfig;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The blazy manager service.
   *
   * @var \Drupal\blazy\BlazyManagerInterface
   */
  protected $blazyManager;

  /**
   * Constructs a BlazyAdminBase object.
   *
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config
   *   The typed config service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\slick\BlazyManagerInterface $blazy_manager
   *   The blazy manager service.
   */
  public function __construct(EntityDisplayRepositoryInterface $entity_display_repository, TypedConfigManagerInterface $typed_config, DateFormatterInterface $date_formatter, BlazyManagerInterface $blazy_manager) {
    $this->entityDisplayRepository = $entity_display_repository;
    $this->typedConfig             = $typed_config;
    $this->dateFormatter           = $date_formatter;
    $this->blazyManager            = $blazy_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_display.repository'), $container->get('config.typed'), $container->get('date.formatter'), $container->get('blazy.manager'));
  }

  /**
   * Returns the entity display repository.
   */
  public function getEntityDisplayRepository() {
    return $this->entityDisplayRepository;
  }

  /**
   * Returns the typed config.
   */
  public function getTypedConfig() {
    return $this->typedConfig;
  }

  /**
   * Returns the blazy manager.
   */
  public function blazyManager() {
    return $this->blazyManager;
  }

  /**
   * Returns shared form elements across field formatter and Views.
   */
  public function openingForm(array &$form, &$definition = []) {
    $this->blazyManager
      ->getModuleHandler()
      ->alter('blazy_form_element_definition', $definition);

    // Display style: column, plain static grid, slick grid, slick carousel.
    // https://drafts.csswg.org/css-multicol
    if (!empty($definition['style'])) {
      $form['style'] = [
        '#type'         => 'select',
        '#title'        => $this->t('Display style'),
        '#description'  => $this->t('Unless otherwise specified, the styles require <strong>Grid</strong>. Difference: <ul><li><strong>Columns</strong> is best with irregular image sizes (scale width, empty height), affects the natural order of grid items, top-bottom, not left-right.</li><li><strong>Foundation</strong> with regular cropped ones, left-right.</li><li><strong>Flex Masonry</strong> (@deprecated due to an epic failure) uses Flexbox, supports (ir)-regular, left-right flow.</li><li><strong>Native Grid</strong> supports both one and two dimensional grid.</li></ul> Unless required, leave empty to use default formatter, or style. Save for <b>Grid Foundation</b>, the rest are experimental!'),
        '#enforced'     => TRUE,
        '#empty_option' => $this->t('- None -'),
        '#options'      => $this->blazyManager->getStyles(),
        '#required' => !empty($definition['grid_required']),
        '#weight'   => -112,
        '#wrapper_attributes' => [
          'class' => [
            'form-item--style',
            'form-item--tooltip-bottom',
          ],
        ],
      ];
    }

    if (!empty($definition['skins'])) {
      $form['skin'] = [
        '#type'        => 'select',
        '#title'       => $this->t('Skin'),
        '#options'     => $definition['skins'],
        '#enforced'    => TRUE,
        '#description' => $this->t('Skins allow various layouts with just CSS. Some options below depend on a skin. Leave empty to DIY. Or use the provided hook_info() and implement the skin interface to register ones.'),
        '#weight'      => -107,
      ];
    }

    if (!empty($definition['background'])) {
      $form['background'] = [
        '#type'        => 'checkbox',
        '#title'       => $this->t('Use CSS background'),
        '#description' => $this->t('Check this to turn the image into CSS background. This opens up the goodness of CSS, such as background cover, fixed attachment, etc. <br /><strong>Important!</strong> Requires an Aspect ratio, otherwise collapsed containers. Unless explicitly removed such as for GridStack which manages its own problem, or a min-height is added manually to <strong>.b-bg</strong> selector.'),
        '#weight'      => -98,
      ];
    }

    if (!empty($definition['layouts'])) {
      $form['layout'] = [
        '#type'        => 'select',
        '#title'       => $this->t('Layout'),
        '#options'     => $definition['layouts'],
        '#description' => $this->t('Requires a skin. The builtin layouts affects the entire items uniformly. Leave empty to DIY.'),
        '#weight'      => 2,
      ];
    }

    if (!empty($definition['captions'])) {
      $form['caption'] = [
        '#type'        => 'checkboxes',
        '#title'       => $this->t('Caption fields'),
        '#options'     => $definition['captions'],
        '#description' => $this->t('Enable any of the following fields as captions. These fields are treated and wrapped as captions.'),
        '#weight'      => 80,
        '#attributes'  => ['class' => ['form-wrapper--caption']],
      ];
    }

    if (!empty($definition['target_type']) && !empty($definition['view_mode'])) {
      $form['view_mode'] = $this->baseForm($definition)['view_mode'];
    }

    $weight = -99;
    foreach (Element::children($form) as $key) {
      if (!isset($form[$key]['#weight'])) {
        $form[$key]['#weight'] = ++$weight;
      }
    }
  }

  /**
   * Returns re-usable grid elements across field formatter and Views.
   */
  public function gridForm(array &$form, $definition = []) {
    $required = !empty($definition['grid_required']);

    $header = $this->t('Group individual items as block grid<small>Depends on the <strong>Display style</strong>.</small>');
    $form['grid_header'] = [
      '#type'   => 'markup',
      '#markup' => '<h3 class="form__title form__title--grid">' . $header . '</h3>',
      '#access' => !$required,
    ];

    if ($required) {
      $description = $this->t('The amount of block grid columns (1 - 12, or empty) for large monitors 64.063em (1025px) up.');
    }
    else {
      $description = $this->t('Empty the value first if trouble with changing form states. The amount of block grid columns (1 - 12, or empty) for large monitors 64.063em  (1025px) up. <br /><strong>Requires</strong>:<ol><li>Any grid-related Display style,</li><li>Visible items,</li><li>Skin Grid for starter,</li><li>A reasonable amount of contents.</li></ol>');
    }

    $form['grid'] = [
      '#type'        => 'textfield',
      '#title'       => $this->t('Grid large'),
      '#description' => $description,
      '#enforced'    => TRUE,
      '#required'    => $required,
      '#wrapper_attributes' => [
        'class' => [
          'form-item--full',
          'form-item--tooltip-bottom',
        ],
      ],
    ];

    $form['grid_medium'] = [
      '#type'        => 'textfield',
      '#title'       => $this->t('Grid medium'),
      '#description' => $this->t('Only accepts uniform columns (1 - 12, or empty) for medium devices 40.063em - 64em (641px - 1024px) up, even for Native Grid due to being pure CSS without JS.'),
    ];

    $form['grid_small'] = [
      '#type'        => 'textfield',
      '#title'       => $this->t('Grid small'),
      '#description' => $this->t('Only accepts uniform columns (1 - 2, or empty) for small devices 0 - 40em (640px) up due to small real estate, even for Native Grid due to being pure CSS without JS. Below this is alway one column.'),
    ];

    $form['visible_items'] = [
      '#type'        => 'select',
      '#title'       => $this->t('Visible items'),
      '#options'     => array_combine(range(1, 32), range(1, 32)),
      '#description' => $this->t('How many items per display at a time.'),
    ];

    $form['preserve_keys'] = [
      '#type'        => 'checkbox',
      '#title'       => $this->t('Preserve keys'),
      '#description' => $this->t('If checked, keys will be preserved. Default is FALSE which will reindex the grid chunk numerically.'),
      '#access'      => FALSE,
    ];

    $grids = [
      'grid_header',
      'grid_medium',
      'grid_small',
      'visible_items',
      'preserve_keys',
    ];

    foreach ($grids as $key) {
      $form[$key]['#enforced'] = TRUE;
      $form[$key]['#states'] = [
        'visible' => [
          'input[name$="[grid]"]' => ['!value' => ''],
        ],
      ];
    }
  }

  /**
   * Returns shared ending form elements across field formatter and Views.
   */
  public function closingForm(array &$form, $definition = []) {
    $this->finalizeForm($form, $definition);
  }

  /**
   * Returns simple form elements common for Views field, EB widget, formatters.
   */
  public function baseForm($definition = []) {
    $settings   = $definition['settings'] ?? [];
    $lightboxes = $this->blazyManager->getLightboxes();
    $namespace  = $definition['namespace'] ?? '';
    $form       = [];
    $ui_url     = '/admin/config/media/blazy';

    if ($this->blazyManager->getModuleHandler()->moduleExists('blazy_ui')) {
      $ui_url = Url::fromRoute('blazy.settings')->toString();
    }

    if (empty($definition['no_image_style'])) {
      $form['preload'] = [
        '#type'        => 'checkbox',
        '#title'       => $this->t('Preload'),
        '#weight'      => -111,
        '#description' => $this->t("Preload to optimize the loading of late-discovered resources. Normally large or hero images below the fold. By preloading a resource, you tell the browser to fetch it sooner than the browser would otherwise discover it before Native lazy or lazyloader JavaScript kicks in, or starts its own preload or decoding. The browser caches preloaded resources so they are available immediately when needed. Nothing is loaded or executed at preloading stage. <br>Just a friendly heads up: do not overuse this option, because not everything are critical, <a href=':url'>read more</a>.", [
          ':url' => 'https://www.drupal.org/node/3262804',
        ]),
        '#wrapper_attributes' => [
          'class' => [
            'form-item--preload',
            'form-item--tooltip-bottom',
          ],
        ],
      ];

      $loadings = ['auto', 'defer', 'eager', 'unlazy'];
      $sliders = in_array($namespace, ['slick', 'splide']);
      if (!empty($definitions['slider']) || $sliders) {
        $loadings[] = 'slider';
      }
      $form['loading'] = [
        '#type'         => 'select',
        '#title'        => $this->t('Loading priority'),
        '#options'      => array_combine($loadings, $loadings),
        '#empty_option' => $this->t('lazy'),
        '#weight'       => -111,
        '#description'  => $this->t("Decide the `loading` attribute affected by the above fold aka onscreen critical contents. <ul><li>`lazy`, the default: defers loading below fold or offscreen images and iframes until users scroll near them.</li><li>`auto`: browser determines whether or not to lazily load. Only if uncertain about the above fold boundaries given different devices. </li><li>`eager`: loads right away. Similar effect like without `loading`, included for completeness. Good for above fold.</li><li>`defer`: trigger native lazy after the first row is loaded. Will disable global `No JavaScript: lazy` option on this particular field, <a href=':defer'>read more</a>.</li><li>`unlazy`: explicitly removes loading attribute enforced by core. Also removes old `data-[SRC|SRCSET|LAZY]` if `No JavaScript` is disabled. Best for the above fold.</li><li>`slider`, if applicable: will `unlazy` the first visible, and leave the rest lazyloaded. Best for sliders (one visible at a time), not carousels (multiple visible slides at once).</li></ul><b>Note</b>: lazy loading images/ iframes for the above fold is anti-pattern, avoid, <a href=':url' target='_blank'>read more</a>.", [
          ':url' => 'https://www.drupal.org/node/3262724',
          ':defer' => 'https://drupal.org/node/3120696',
        ]),
        '#wrapper_attributes' => [
          'class' => [
            'form-item--loading',
            'form-item--tooltip-bottom',
          ],
        ],
      ];

      $form['image_style'] = [
        '#type'        => 'select',
        '#title'       => $this->t('Image style'),
        '#options'     => $this->getEntityAsOptions('image_style'),
        '#weight'      => -100,
        '#description' => $this->t('The content image style. This will be treated as the fallback image to override the global option <a href=":url">Responsive image 1px placeholder</a>, which is normally smaller, if Responsive image are provided. Shortly, leave it empty to make Responsive image fallback respected. Otherwise this is the only image displayed. This image style is also used to provide dimensions not only for image/iframe but also any media entity like local video, where no images are even associated with, to have the designated dimensions in tandem with aspect ratio as otherwise no UI to customize for.', [':url' => $ui_url]),
        '#wrapper_attributes' => [
          'class' => [
            'form-item--image-style',
            'form-item--tooltip-bottom',
          ],
        ],
      ];
    }

    if (isset($settings['media_switch'])) {
      $form['media_switch'] = [
        '#type'         => 'select',
        '#title'        => $this->t('Media switcher'),
        '#options'      => [
          'content' => $this->t('Image linked to content'),
        ],
        '#empty_option' => $this->t('- None -'),
        '#description'  => $this->t('Clear cache if lightboxes do not appear here due to being permanently cached. <ol><li>Link to content: for aggregated small slicks.</li><li>Image to iframe: video is hidden below image until toggled, otherwise iframe is always displayed, and draggable fails. Aspect ratio applies.</li><li>(Quasi-)lightboxes: Colorbox, ElevateZoomPlus, Intense, Photobox, PhotoSwipe, Magnific Popup, Slick Lightbox, Splidebox, Zooming, etc. Depends on the enabled supported modules, or has known integration with Blazy. See docs or <em>/admin/help/blazy_ui</em> for details.</li></ol> Add <em>Thumbnail style</em> if using Photobox, Slick, or others which may need it. Try selecting "<strong>- None -</strong>" first before changing if trouble with this complex form states.'),
        '#weight'       => -99,
      ];

      // Optional lightbox integration.
      if (!empty($lightboxes)) {
        foreach ($lightboxes as $lightbox) {
          $name = Unicode::ucwords(str_replace('_', ' ', $lightbox));
          if ($lightbox == 'photobox') {
            $name .= ' (Deprecated)';
          }
          if ($lightbox == 'mfp') {
            $name = 'Magnific Popup';
          }
          $form['media_switch']['#options'][$lightbox] = $this->t('Image to @lightbox', ['@lightbox' => $name]);
        }

        // Re-use the same image style for both lightboxes.
        $form['box_style'] = [
          '#type'        => 'select',
          '#title'       => $this->t('Lightbox image style'),
          '#options'     => $this->getResponsiveImageOptions() + $this->getEntityAsOptions('image_style'),
          '#weight'      => -97,
          '#description' => $this->t('Supports both Responsive and regular images.'),
        ];

        if (!empty($definition['multimedia'])) {
          $form['box_media_style'] = [
            '#type'        => 'select',
            '#title'       => $this->t('Lightbox video style'),
            '#options'     => $this->getEntityAsOptions('image_style'),
            '#description' => $this->t('Allows different lightbox video dimensions. Or can be used to have a swipable video if <a href=":url1">Blazy PhotoSwipe</a> or <a href=":url2">Slick Lightbox</a> installed.', [
              ':url1' => 'https:drupal.org/project/blazy_photoswipe',
              ':url2' => 'https:drupal.org/project/slick_lightbox',
            ]),
            '#weight'      => -96,
          ];
        }

        if (empty($definition['box_stateless'])) {
          foreach (['box_style', 'box_media_style'] as $key) {
            if (isset($form[$key])) {
              $form[$key]['#states'] = $this->getState(static::STATE_LIGHTBOX_ENABLED, $definition);
            }
          }
        }
      }

      // Adds common supported entities for media integration.
      if (!empty($definition['multimedia'])) {
        $form['media_switch']['#options']['media'] = $this->t('Image to iFrame');
      }

      // http://en.wikipedia.org/wiki/List_of_common_resolutions
      $ratio = ['1:1', '3:2', '4:3', '8:5', '16:9', 'fluid'];
      if (empty($definition['no_ratio'])) {
        $form['ratio'] = [
          '#type'         => 'select',
          '#title'        => $this->t('Aspect ratio'),
          '#options'      => array_combine($ratio, $ratio),
          '#empty_option' => $this->t('- None -'),
          '#description'  => $this->t('Aspect ratio to get consistently responsive images and iframes. Coupled with Image style. And to fix layout reflow, excessive height issues, whitespace below images, collapsed container, no-js users, etc. <a href="@dimensions" target="_blank">Image styles and video dimensions</a> must <a href="@follow" target="_blank">follow the aspect ratio</a>. If not, images will be distorted. <a href="@link" target="_blank">Learn more</a>. <ul><li><b>Fixed ratio:</b> all images use the same aspect ratio mobile up. Use it to avoid JS works, or if it fails Responsive image. </li><li><b>Fluid:</b> aka dynamic, dimensions are calculated and JS works are attempted to fix it.</li><li><b>Leave empty:</b> to DIY (such as using CSS mediaquery), or when working with multi-image-style plugin like GridStack.</li></ul>', [
            '@dimensions'  => '//size43.com/jqueryVideoTool.html',
            '@follow'      => '//en.wikipedia.org/wiki/Aspect_ratio_%28image%29',
            '@link'        => '//www.smashingmagazine.com/2014/02/27/making-embedded-content-work-in-responsive-design/',
          ]),
          '#weight'        => -95,
        ];
      }
    }

    if (!empty($definition['target_type']) && !empty($definition['view_mode'])) {
      $form['view_mode'] = [
        '#type'        => 'select',
        '#options'     => $this->getViewModeOptions($definition['target_type']),
        '#title'       => $this->t('View mode'),
        '#description' => $this->t('Required to grab the fields, or to have custom entity display as fallback display. If it has fields, be sure the selected "View mode" is enabled, and the enabled fields here are not hidden there.'),
        '#weight'      => -94,
        '#enforced'    => TRUE,
      ];

      if ($this->blazyManager->getModuleHandler()->moduleExists('field_ui')) {
        $form['view_mode']['#description'] .= ' ' . $this->t('Manage view modes on the <a href=":view_modes">View modes page</a>.', [':view_modes' => Url::fromRoute('entity.entity_view_mode.collection')->toString()]);
      }
    }

    if (!empty($definition['thumbnail_style'])) {
      $form['thumbnail_style'] = [
        '#type'        => 'select',
        '#title'       => $this->t('Thumbnail style'),
        '#options'     => $this->getEntityAsOptions('image_style'),
        '#description' => $this->t('Usages: Placeholder replacement for image effects (blur, etc.), Photobox/PhotoSwipe thumbnail, or custom work with thumbnails. Be sure to have similar aspect ratio for the best blur effect. Leave empty to not use thumbnails.'),
        '#weight'      => -96,
      ];
    }

    // @todo this can also be used for local video poster image option.
    if (isset($definition['images'])) {
      $form['image'] = [
        '#type'        => 'select',
        '#title'       => $this->t('Main stage'),
        '#options'     => is_array($definition['images']) ? $definition['images'] : [],
        '#description' => $this->t('Main background/stage/poster image field with the only supported field types: <b>Image</b> or <b>Media</b> containing Image field. You may want to add a new Image field to this entity.'),
        '#prefix'      => '<h3 class="form__title form__title--fields">' . $this->t('Fields') . '</h3>',
      ];
    }

    $this->blazyManager->getModuleHandler()->alter('blazy_base_form_element', $form, $definition);

    return $form;
  }

  /**
   * Returns re-usable media switch form elements.
   */
  public function mediaSwitchForm(array &$form, $definition = []) {
    $settings   = $definition['settings'] ?? [];
    $lightboxes = $this->blazyManager->getLightboxes();
    $is_token   = $this->blazyManager->getModuleHandler()->moduleExists('token');

    if (isset($settings['media_switch'])) {
      $form['media_switch'] = $this->baseForm($definition)['media_switch'];
      $form['media_switch']['#prefix'] = '<h3 class="form__title form__title--media-switch">' . $this->t('Media switcher') . '</h3>';

      if (empty($definition['no_ratio'])) {
        $form['ratio'] = $this->baseForm($definition)['ratio'];
      }
    }

    // Optional lightbox integration.
    if (!empty($lightboxes) && isset($settings['media_switch'])) {
      $form['box_style'] = $this->baseForm($definition)['box_style'];

      if (!empty($definition['multimedia'])) {
        $form['box_media_style'] = $this->baseForm($definition)['box_media_style'];
      }

      if (!empty($definition['box_captions'])) {
        $form['box_caption'] = [
          '#type'        => 'select',
          '#title'       => $this->t('Lightbox caption'),
          '#options'     => $this->getLightboxCaptionOptions(),
          '#weight'      => -95,
          '#description' => $this->t('Automatic will search for Alt text first, then Title text. Try selecting <strong>- None -</strong> first when changing if trouble with form states.'),
        ];

        if (empty($definition['box_stateless'])) {
          $form['box_caption']['#states'] = $this->getState(static::STATE_LIGHTBOX_ENABLED, $definition);
        }

        $form['box_caption_custom'] = [
          '#title'       => $this->t('Lightbox custom caption'),
          '#type'        => 'textfield',
          '#weight'      => -94,
          '#states'      => $this->getState(static::STATE_LIGHTBOX_CUSTOM, $definition),
          '#description' => $this->t('Multi-value rich text field will be mapped to each image by its delta.'),
        ];

        if ($is_token) {
          $types = isset($definition['entity_type']) ? [$definition['entity_type']] : [];
          $types = isset($definition['target_type']) ? array_merge($types, [$definition['target_type']]) : $types;

          if ($types) {
            $form['box_caption_custom']['#field_suffix'] = [
              '#theme'       => 'token_tree_link',
              '#text'        => $this->t('Tokens'),
              '#token_types' => $types,
            ];
          }
        }
      }
    }

    $this->blazyManager->getModuleHandler()->alter('blazy_media_switch_form_element', $form, $definition);
  }

  /**
   * Returns re-usable logic, styling and assets across fields and Views.
   */
  public function finalizeForm(array &$form, $definition = []) {
    $namespace = $definition['namespace'] ?? 'slick';
    $settings = $definition['settings'] ?? [];
    $vanilla = !empty($definition['vanilla']) ? ' form--vanilla' : '';
    $grid = !empty($definition['grid_required']) ? ' form--grid-required' : '';
    $plugind_id = !empty($definition['plugin_id']) ? ' form--plugin-' . str_replace('_', '-', $definition['plugin_id']) : '';
    $count = empty($definition['captions']) ? 0 : count($definition['captions']);
    $count = empty($definition['captions_count']) ? $count : $definition['captions_count'];
    $wide = $count > 2 ? ' form--wide form--caption-' . $count : ' form--caption-' . $count;
    $fallback = $namespace == 'slick' ? 'form--slick' : 'form--' . $namespace . ' form--slick';
    $plugins = ' form--namespace-' . $namespace;
    $custom = $definition['opening_class'] ?? '';
    $classes = ($fallback . ' form--half has-tooltip' . $wide . $vanilla . $grid . $plugind_id . ' ' . $custom . $plugins);

    if (!empty($definition['field_type'])) {
      $classes .= ' form--' . str_replace('_', '-', $definition['field_type']);
    }

    if (isset($form['grid'], $form['grid']['#description'])) {
      $description = $form['grid']['#description'];
      $form['grid']['#description'] = $description . $this->nativeGridDescription();
    }

    $form['opening'] = [
      '#markup' => '<div class="' . $classes . '">',
      '#weight' => -120,
    ];

    $form['closing'] = [
      '#markup' => '</div>',
      '#weight' => 120,
    ];

    // @todo Check if needed: 'button', 'container', 'submit'.
    $admin_css = $definition['admin_css'] ?? FALSE;
    $admin_css = $admin_css ?: $this->blazyManager->configLoad('admin_css', 'blazy.settings');
    $excludes = ['details', 'fieldset', 'hidden', 'markup', 'item', 'table'];
    $selects = ['cache', 'optionset', 'view_mode'];

    // Disable the admin css in the off canvas menu, to avoid conflicts with
    // the active frontend theme.
    if ($admin_css && $router = Path::requestStack()) {
      $wrapper_format = $router->getCurrentRequest()->query->get('_wrapper_format');

      if (!empty($wrapper_format) && $wrapper_format === "drupal_dialog.off_canvas") {
        $admin_css = FALSE;
      }
    }

    $this->blazyManager->getModuleHandler()->alter('blazy_form_element', $form, $definition);

    foreach (Element::children($form) as $key) {
      if (isset($form[$key]['#type']) && !in_array($form[$key]['#type'], $excludes)) {
        if (!isset($form[$key]['#default_value']) && isset($settings[$key])) {
          $value = is_array($settings[$key]) ? array_values((array) $settings[$key]) : $settings[$key];

          if (!empty($definition['grid_required']) && $key == 'grid' && empty($settings[$key])) {
            $value = 3;
          }
          $form[$key]['#default_value'] = $value;
        }
        if (!isset($form[$key]['#attributes']) && isset($form[$key]['#description'])) {
          $form[$key]['#attributes'] = ['class' => ['is-tooltip']];
        }

        if ($admin_css) {
          if ($form[$key]['#type'] == 'checkbox' && $form[$key]['#type'] != 'checkboxes') {
            $form[$key]['#field_suffix'] = '&nbsp;';
            $form[$key]['#title_display'] = 'before';
          }
          elseif ($form[$key]['#type'] == 'checkboxes' && !empty($form[$key]['#options'])) {
            $form[$key]['#attributes']['class'][] = 'form-wrapper--checkboxes';
            $form[$key]['#attributes']['class'][] = 'form-wrapper--' . str_replace('_', '-', $key);
            $count = count($form[$key]['#options']);
            $form[$key]['#attributes']['class'][] = 'form-wrapper--count-' . ($count > 3 ? 'max' : $count);

            foreach ($form[$key]['#options'] as $i => $option) {
              $form[$key][$i]['#field_suffix'] = '&nbsp;';
              $form[$key][$i]['#title_display'] = 'before';
            }
          }
        }

        if ($form[$key]['#type'] == 'select' && !in_array($key, $selects)) {
          if (!isset($form[$key]['#empty_option']) && empty($form[$key]['#required'])) {
            $form[$key]['#empty_option'] = $this->t('- None -');
          }
          if (!empty($form[$key]['#required'])) {
            unset($form[$key]['#empty_option']);
          }
        }

        if (!isset($form[$key]['#enforced']) && !empty($definition['vanilla']) && isset($form[$key]['#type'])) {
          $states['visible'][':input[name*="[vanilla]"]'] = ['checked' => FALSE];
          if (isset($form[$key]['#states'])) {
            $form[$key]['#states']['visible'][':input[name*="[vanilla]"]'] = ['checked' => FALSE];
          }
          else {
            $form[$key]['#states'] = $states;
          }
        }
      }

      $form[$key]['#wrapper_attributes']['class'][] = 'form-item--' . str_replace('_', '-', $key);

      if (isset($form[$key]['#access']) && $form[$key]['#access'] == FALSE) {
        unset($form[$key]['#default_value']);
      }

      if (in_array($key, BlazyDefault::deprecatedSettings())) {
        unset($form[$key]['#default_value']);
      }
    }

    if ($admin_css) {
      $form['closing']['#attached']['library'][] = 'blazy/admin';
    }

    $this->blazyManager->getModuleHandler()->alter('blazy_complete_form_element', $form, $definition);
  }

  /**
   * Returns time in interval for select options.
   */
  public function getCacheOptions() {
    $period = [
      0,
      60,
      180,
      300,
      600,
      900,
      1800,
      2700,
      3600,
      10800,
      21600,
      32400,
      43200,
      86400,
    ];

    $period = array_map([$this->dateFormatter, 'formatInterval'],
      array_combine($period, $period));
    $period[0] = '<' . $this->t('No caching') . '>';
    return $period + [Cache::PERMANENT => $this->t('Permanent')];
  }

  /**
   * Returns available lightbox captions for select options.
   */
  public function getLightboxCaptionOptions() {
    return [
      'auto'         => $this->t('Automatic'),
      'alt'          => $this->t('Alt text'),
      'title'        => $this->t('Title text'),
      'alt_title'    => $this->t('Alt and Title'),
      'title_alt'    => $this->t('Title and Alt'),
      'entity_title' => $this->t('Content title'),
      'custom'       => $this->t('Custom'),
    ];
  }

  /**
   * Returns available entities for select options.
   */
  public function getEntityAsOptions($entity_type = '') {
    $options = [];
    if ($entities = $this->blazyManager->entityLoadMultiple($entity_type)) {
      foreach ($entities as $entity) {
        $options[$entity->id()] = Html::escape($entity->label());
      }
      ksort($options);
    }
    return $options;
  }

  /**
   * Returns available optionsets for select options.
   */
  public function getOptionsetOptions($entity_type = '') {
    return $this->getEntityAsOptions($entity_type);
  }

  /**
   * Returns available view modes for select options.
   */
  public function getViewModeOptions($target_type) {
    return $this->entityDisplayRepository->getViewModeOptions($target_type);
  }

  /**
   * Returns Responsive image for select options.
   */
  public function getResponsiveImageOptions() {
    $options = [];
    if ($this->blazyManager()->getModuleHandler()->moduleExists('responsive_image')) {
      $image_styles = $this->blazyManager()->entityLoadMultiple('responsive_image_style');
      if (!empty($image_styles)) {
        foreach ($image_styles as $name => $image_style) {
          if ($image_style->hasImageStyleMappings()) {
            $options[$name] = Html::escape($image_style->label());
          }
        }
      }
    }
    return $options;
  }

  /**
   * Returns native grid description.
   */
  protected function nativeGridDescription() {
    return $this->t('<br>Specific for <b>Native Grid</b>, two recipes: <ol><li><b>One-dimensional</b>: Input a single numeric column grid, acting as Masonry. <em>Best with</em>: scaled pictures.</li><li><b>Two-dimensional</b>: Input a space separated value with <code>WIDTHxHEIGHT</code> pair based on the amount of columns/ rows, at max 12, e.g.: <br><code>4x4 4x3 2x2 2x4 2x2 2x3 2x3 4x2 4x2</code> <br>This will resemble GridStack optionset <b>Tagore</b>. Any single value e.g.: <code>4x4</code> will repeat uniformly like one-dimesional. <br><em>Best with</em>: <ul><li><b>Use CSS background</b> ON.</li><li>Exact item amount or better more designated grids than lacking. Use a little math with the exact item amount to have gapless grids.</li><li>Disabled image aspect ratio to use grid ratio instead.</li></ul></li></ol>This requires any grid-related <b>Display style</b>. Unless required, leave empty to DIY, or to not build grids.');
  }

  /**
   * Get one of the pre-defined states used in this form.
   *
   * Thanks to SAM152 at colorbox.module for the little sweet idea.
   *
   * @param string $state
   *   The state to get that matches one of the state class constants.
   * @param array $definition
   *   The foem definitions or settings.
   *
   * @return array
   *   A corresponding form API state.
   */
  protected function getState($state, array $definition = []) {
    $lightboxes = [];

    // @fixme this appears to be broken at some point of Drupal.
    foreach ($this->blazyManager->getLightboxes() as $key => $lightbox) {
      $lightboxes[$key]['value'] = $lightbox;
    }

    $states = [
      static::STATE_RESPONSIVE_IMAGE_STYLE_DISABLED => [
        'visible' => [
          'select[name$="[responsive_image_style]"]' => ['value' => ''],
        ],
      ],
      static::STATE_LIGHTBOX_ENABLED => [
        'visible' => [
          'select[name*="[media_switch]"]' => $lightboxes,
        ],
      ],
      static::STATE_LIGHTBOX_CUSTOM => [
        'visible' => [
          'select[name$="[box_caption]"]' => ['value' => 'custom'],
          // @fixme 'select[name*="[media_switch]"]' => $lightboxes,
        ],
      ],
      static::STATE_IFRAME_ENABLED => [
        'visible' => [
          'select[name*="[media_switch]"]' => ['value' => 'media'],
        ],
      ],
      static::STATE_THUMBNAIL_STYLE_ENABLED => [
        'visible' => [
          'select[name$="[thumbnail_style]"]' => ['!value' => ''],
        ],
      ],
      static::STATE_IMAGE_RENDERED_ENABLED => [
        'visible' => [
          'select[name$="[media_switch]"]' => ['!value' => 'rendered'],
        ],
      ],
    ];
    return $states[$state];
  }

  /**
   * Deprecated method to remove.
   *
   * @todo remove once sub-modules remove this method.
   * @see https://www.drupal.org/node/3105243
   */
  public function breakpointsForm(array &$form, $definition = []) {}

}
