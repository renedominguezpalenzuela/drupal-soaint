<?php

namespace Drupal\blazy\Plugin\Filter;

use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\Xss;
use Drupal\blazy\Blazy;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\Media\BlazyFile;
use Drupal\blazy\Media\BlazyImage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides base filter class.
 */
abstract class BlazyFilterBase extends TextFilterBase implements BlazyFilterInterface {

  /**
   * The blazy admin service.
   *
   * @var \Drupal\blazy\Form\BlazyAdminInterface
   */
  protected $blazyAdmin;

  /**
   * The blazy oembed service.
   *
   * @var \Drupal\blazy\Media\BlazyOEmbedInterface
   */
  protected $blazyOembed;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $instance->blazyAdmin = $instance->blazyAdmin ?? $container->get('blazy.admin');
    $instance->blazyOembed = $instance->blazyOembed ?? $container->get('blazy.oembed');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettings($text) {
    $settings = &$this->settings;
    $settings += BlazyDefault::lazySettings();

    Blazy::verify($settings);

    $settings['plugin_id'] = $plugin_id = $this->getPluginId();
    $settings['id'] = $id = BlazyFilterUtil::getId($plugin_id);

    $definitions = $this->entityFieldManager->getFieldDefinitions('media', 'remote_video');
    $is_media_library = $definitions && isset($definitions['field_media_oembed_video']);

    $this->preSettings($settings, $text);
    $this->blazyManager->preSettings($settings);

    $blazies = $settings['blazies'];
    $blazies->set('is.filter', TRUE)
      ->set('is.media_library', $is_media_library)
      ->set('is.unsafe', TRUE)
      ->set('libs.filter', TRUE);

    $this->postSettings($settings);
    $this->blazyManager->postSettings($settings);

    $blazies->set('lightbox.gallery_id', $id)
      ->set('css.id', $id)
      ->set('filter.plugin_id', $plugin_id);

    return $settings;
  }

  /**
   * Returns the faked image item for the image, uploaded or hard-coded.
   *
   * @param array $build
   *   The content array being modified.
   * @param object $node
   *   The HTML DOM object.
   * @param int $delta
   *   The item index.
   */
  protected function buildImageItem(array &$build, &$node, $delta = 0) {
    $settings = &$build['settings'];
    $blazies = $settings['blazies'];
    $src = BlazyFilterUtil::getValidSrc($node);

    if ($src) {
      if ($node->tagName == 'img') {
        $this->getImageItemFromImageSrc($build, $node, $src);
      }
      elseif ($node->tagName == 'iframe') {
        try {
          // Prevents invalid video URL (404, etc.) from screwing up.
          $this->getImageItemFromIframeSrc($build, $node, $src);
        }
        catch (\Exception $ignore) {
          // Do nothing, likely local work without internet, or the site is
          // down. No need to be chatty on this.
        }
      }
    }

    $item = $build['item'] ?? NULL;
    if ($item) {
      $item->alt = $node->getAttribute('alt') ?: ($item->alt ?? '');
      $item->title = $node->getAttribute('title') ?: ($item->title ?? '');

      // Supports hard-coded image url without file API.
      if ($uri = BlazyFile::uri($item)) {
        $settings['uri'] = $uri;
        $blazies->set('image.uri', $uri);

        // @todo remove.
        if (empty($item->width) && $data = @getimagesize($uri)) {
          [$item->width, $item->height] = $data;
        }
      }
    }

    $build['item'] = $item;
  }

  /**
   * {@inheritdoc}
   */
  public function buildImageCaption(array &$build, &$node) {
    $settings = &$build['settings'];
    $blazies = $settings['blazies'];
    $item = $this->getCaptionElement($node);

    // Sanitization was done by Caption filter when arriving here, as
    // otherwise we cannot see this figure, yet provide fallback.
    if ($item) {
      if ($text = $item->ownerDocument->saveXML($item)) {
        $settings = &$build['settings'];
        $markup = Xss::filter(trim($text), BlazyDefault::TAGS);

        // Supports other caption source if not using Filter caption.
        if (empty($build['captions'])) {
          $build['captions']['alt'] = ['#markup' => $markup];
        }

        if (($settings['box_caption'] ?? '') == 'inline') {
          $settings['box_caption'] = $markup;
        }

        $blazies->set('is.figcaption', TRUE);

        $this->cleanupImageCaption($build, $node, $item);
      }
    }
    return $item;
  }

  /**
   * Returns the expected caption DOMElement.
   */
  protected function getCaptionElement($node) {
    if ($node->parentNode && $node->parentNode->tagName === 'figure') {
      $caption = $node->parentNode->getElementsByTagName('figcaption');
      return ($caption && $caption->item(0)) ? $caption->item(0) : NULL;
    }
    return NULL;
  }

  /**
   * Returns the fallback caption DOMElement for Splide/ Slick, etc.
   */
  protected function getCaptionFallback($node) {
    $caption = NULL;

    // @todo figure out better traversal with DOM.
    if ($node->parentNode) {
      $parent = $node->parentNode->parentNode;
      if ($parent && $grandpa = $parent->parentNode) {
        if ($grandpa->parentNode) {
          $divs = $grandpa->parentNode->getElementsByTagName('div');
        }
        else {
          $divs = $grandpa->getElementsByTagName('div');
        }

        if ($divs) {
          foreach ($divs as $div) {
            $class = $div->getAttribute('class');
            if ($class == 'blazy__caption') {
              $caption = $div;
              break;
            }
          }
        }
      }
    }
    return $caption;
  }

  /**
   * Cleanups image caption.
   */
  protected function cleanupImageCaption(array &$build, &$node, &$item) {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function getImageItemFromImageSrc(array &$build, $node, $src) {
    $settings = &$build['settings'];
    $blazies = $settings['blazies'];
    // Attempts to get the correct URI with hard-coded URL if applicable.
    $uri = $settings['uri'] = BlazyFile::buildUri($src);
    $uuid = $node->getAttribute('data-entity-uuid');
    $blazies->set('entity.uuid', $uuid);

    $file = BlazyFile::item(NULL, $settings);

    // Uploaded image has UUID with file API.
    if (BlazyFile::isFile($file)) {
      $uuid = $uuid ?: $file->uuid();

      if ($item = BlazyImage::fromAny($file, $settings)) {
        $blazies->set('entity.uuid', $uuid);
        $build['item'] = $item;
      }
    }
    else {
      // Manually hard-coded image has no UUID, nor file API.
      // URI validity is not crucial, URL is the bare minimum for Blazy to work.
      $settings['uri'] = $uri ?: $src;

      if ($uri) {
        $build['item'] = BlazyImage::fake($settings);
      }
      else {
        // At least provide root URI to figure out image dimensions.
        $settings['uri_root'] = mb_substr($src, 0, 4) === 'http' ? $src : $this->root . $src;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getImageItemFromIframeSrc(array &$build, &$node, $src) {
    $settings = &$build['settings'];
    $blazies = $settings['blazies'];

    // Iframe with data: alike scheme is a serious kidding, strip it earlier.
    $blazies->set('media.input_url', $src);
    $this->blazyOembed->checkInputUrl($settings);

    // @todo figure out to not hard-code `field_media_oembed_video`.
    $media = NULL;
    if ($src && $blazies->is('media_library')) {
      $media = $this->blazyManager->loadByProperties([
        'field_media_oembed_video' => $src,
      ], 'media', TRUE);

      $media = reset($media);
    }

    // Runs after type, width and height set, if any, to not recheck them.
    $this->blazyOembed->build($build, $media);
  }

  /**
   * Provides the grid item attributes, and caption, if any.
   */
  protected function buildItemAttributes(array &$build, $node, $delta = 0) {
    $sets = &$build['settings'];
    $blazies = $sets['blazies'];
    $blazies->set('is.blazy_tag', TRUE);

    if ($caption = $node->getAttribute('caption')) {
      $build['captions']['alt'] = ['#markup' => $this->filterHtml($caption)];
      $node->removeAttribute('caption');
    }

    if ($attributes = BlazyFilterUtil::getAttribute($node)) {
      // Move it to .grid__content for better displays like .well/ .card.
      if (!empty($attributes['class'])) {
        $build['content_attributes']['class'] = $attributes['class'];
        unset($attributes['class']);
      }
      $build['attributes'] = $attributes;
    }
  }

  /**
   * Returns the item settings for the current $node.
   *
   * @param array $build
   *   The settings being modified.
   * @param object $node
   *   The HTML DOM object.
   * @param int $delta
   *   The item index.
   */
  protected function buildItemSettings(array &$build, $node, $delta = 0) {
    $settings = &$build['settings'];
    $blazies = $settings['blazies'];

    // Set an image style based on node data properties.
    // See https://www.drupal.org/project/drupal/issues/2061377,
    // https://www.drupal.org/project/drupal/issues/2822389, and
    // https://www.drupal.org/project/inline_responsive_images.
    $update = FALSE;
    if ($style = $node->getAttribute('data-image-style')) {
      $update = TRUE;
      $settings['image_style'] = $style;
    }

    if ($blazies->is('resimage')
      && $style = $node->getAttribute('data-responsive-image-style')) {
      $update = TRUE;
      $settings['responsive_image_style'] = $style;
    }

    foreach (['width', 'height'] as $key) {
      if ($value = $node->getAttribute($key)) {
        $settings[$key] = $value;
        $blazies->set('image.' . $key, $value);
      }
    }

    if ($update) {
      $blazies->set('is.multistyle', TRUE);
      // Checks for image styles at individual items, normally set at container.
      // Responsive image is at item level due to requiring URI detection.
      BlazyImage::styles($settings, TRUE);
    }
  }

  /**
   * Build the individual item content.
   *
   * @param array $build
   *   The content array being modified.
   * @param object $node
   *   The HTML DOM object.
   * @param int $delta
   *   The item index.
   */
  protected function buildItemContent(array &$build, $node, $delta = 0) {
    // Provides individual item settings.
    $this->buildItemSettings($build, $node, $delta);

    // Extracts image item from SRC attribute.
    $this->buildImageItem($build, $node, $delta);

    // Extracts image caption if available.
    $this->buildImageCaption($build, $node);
  }

  /**
   * Provides media switch form.
   */
  protected function mediaSwitchForm(array &$form) {
    $lightboxes = $this->blazyManager->getLightboxes();

    $form['media_switch'] = [
      '#type' => 'select',
      '#title' => $this->t('Media switcher'),
      '#options' => [
        'media' => $this->t('Image to iframe'),
      ],
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->settings['media_switch'] ?? '',
      '#description' => $this->t('<ul><li><b>Image to iframe</b> will play video when toggled.</li><li><b>Image to lightbox</b> (Colorbox, Photobox, PhotoSwipe, Slick Lightbox, Zooming, Intense, etc.) will display media in lightbox.</li></ul>Both can stand alone or grouped as a gallery. To build a gallery, use the grid shortcodes.'),
    ];

    if (!empty($lightboxes)) {
      foreach ($lightboxes as $lightbox) {
        $name = Unicode::ucwords(str_replace('_', ' ', $lightbox));
        $form['media_switch']['#options'][$lightbox] = $this->t('Image to @lightbox', ['@lightbox' => $name]);
      }
    }

    $styles = $this->blazyAdmin->getResponsiveImageOptions()
      + $this->blazyAdmin->getEntityAsOptions('image_style');

    $form['hybrid_style'] = [
      '#type' => 'select',
      '#title' => $this->t('(Responsive) image style'),
      '#options' => $styles,
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->settings['hybrid_style'] ?? '',
      '#description' => $this->t('Fallback (Responsive) image style when <code>[data-image-style]</code> or <code>[data-responsive-image-style]</code> attributes are not present, see https://drupal.org/node/2061377.'),
    ];

    $form['box_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Lightbox (Responsive) image style'),
      '#options' => $styles,
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->settings['box_style'] ?? '',
    ];

    $captions = $this->blazyAdmin->getLightboxCaptionOptions();
    unset($captions['entity_title'], $captions['custom']);
    $form['box_caption'] = [
      '#type' => 'select',
      '#title' => $this->t('Lightbox caption'),
      '#options' => $captions + ['inline' => $this->t('Caption filter')],
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->settings['box_caption'] ?? '',
      '#description' => $this->t('Automatic will search for Alt text first, then Title text. <br>Image styles only work for uploaded images, not hand-coded ones. Caption filter will use <code>data-caption</code> normally managed by Caption filter.'),
    ];
  }

  /**
   * Extracts setting from attributes.
   *
   * @todo deprecated at 2.9 and removed from 3.x. Use
   * self::extractSettings() instead.
   */
  protected function prepareSettings(\DOMElement $node, array &$settings) {
    $this->extractSettings($node, $settings);
  }

}
