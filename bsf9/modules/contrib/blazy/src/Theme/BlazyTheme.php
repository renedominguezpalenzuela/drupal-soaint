<?php

namespace Drupal\blazy\Theme;

use Drupal\Component\Utility\Html;
use Drupal\Core\Template\Attribute;
use Drupal\blazy\Blazy;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\Media\Placeholder;
use Drupal\blazy\Utility\Path;

/**
 * Provides theme-related alias methods to de-clutter Blazy.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 */
class BlazyTheme {

  /**
   * Overrides variables for blazy.html.twig templates.
   *
   * Most heavy liftings are performed at BlazyManager::preRender().
   *
   * @param array $variables
   *   An associative array containing:
   *   - captions: An optional renderable array of inline or lightbox captions.
   *   - item: The image item containing alt, title, etc.
   *   - image: An optional renderable array of (Responsive) image element.
   *       Image is optional for CSS background, or iframe only displays.
   *   - settings: HTML related settings containing at least a required uri.
   *   - url: An optional URL the image can be linked to, can be any of
   *       audio/video, or entity URLs, when using Colorbox/Photobox, or Link to
   *       content options.
   *   - attributes: The container attributes (media, media--ratio etc.).
   *   - item_attributes: The image attributes (width, height, src, etc.).
   *   - url_attributes: An array of URL attributes, lightbox or content links.
   *   - noscript: The fallback image for non-js users.
   *   - postscript: Any extra content to put into blazy goes here. Use keyed or
   *       indexed array to not conflict with or nullify other providers, e.g.:
   *       postscript.cta, or postscript.widget. Avoid postscript = cta.
   *   - content: Various Media entities like Facebook, Instagram, local Video,
   *       etc. Basically content is the replacement for (Responsive) image
   *       and oEmbed video. This makes it possible to have a mix of Media
   *       entities, image and video on a Blazy Grid, Slick, GridStack, etc.
   *       without having different templates. Originally content is a
   *       theme_field() output, trimmed down to bare minimum so that embeddable
   *       inside theme_blazy() without duplicated or nested field markups.
   *       Regular Blazy features are still disabled by default at
   *       \Drupal\blazy\BlazyDefault::richSettings() to avoid complication.
   *       However you can override them accordingly as needed, such as lightbox
   *       for local Video with/o a pre-configured poster image. The #settings
   *       are provided under content variables for more work.
   */
  public static function blazy(array &$variables): void {
    $element = $variables['element'];
    foreach (BlazyDefault::themeProperties() as $key) {
      $variables[$key] = $element["#$key"] ?? [];
    }

    // Provides optional attributes, see BlazyFilter.
    foreach (BlazyDefault::themeAttributes() as $key) {
      $key = $key . '_attributes';
      $variables[$key] = empty($element["#$key"]) ? [] : new Attribute($element["#$key"]);
    }

    // Provides sensible default html settings to shutup notices when lacking.
    $attributes = &$variables['attributes'];
    $settings = &$variables['settings'];
    $settings += BlazyDefault::itemSettings();
    $blazies = $settings['blazies'];
    $item = $variables['item'];
    $api = $blazies->is('api');

    // Still provides a failsafe for direct call to theme_blazy().
    if (!$api) {
      Blazy::preSettings($settings);
      Blazy::prepare($settings, $item, $settings['delta'] ?? 0);
    }

    // Do not proceed if no URI is provided. URI is not Blazy theme property.
    // Blazy is a wrapper for theme_[(responsive_)image], etc. who wants URI.
    if (!$blazies->get('image.uri')) {
      return;
    }

    // URL and dimensions are built out at BlazyManager::preRenderBlazy().
    // Still provides a failsafe for direct call to theme_blazy().
    if (!$api) {
      Blazy::prepared($attributes, $settings, $item);
    }

    // Allows rich Media entities stored within `content` to take over.
    // Rich media are things Blazy don't understand: Instagram, Facebook, etc.
    if (empty($variables['content'])) {
      BlazyAttribute::buildMedia($variables);
    }

    // Aspect ratio to fix layout reflow with lazyloaded images responsively.
    // This is outside 'lazy' to allow non-lazyloaded iframe/content use it too.
    // Prevents double padding hacks with AMP which also uses similar technique.
    BlazyAttribute::finalize($variables);

    // Still provides a failsafe for direct call to theme_blazy().
    if (!$api) {
      Blazy::attach($variables, $settings);
    }
  }

  /**
   * Overrides variables for field.html.twig templates.
   */
  public static function field(array &$variables): void {
    $element = &$variables['element'];
    $settings = empty($element['#blazy']) ? [] : $element['#blazy'];
    $blazies = $settings['blazies'] ?? NULL;

    // 1. Hence Blazy is not the formatter, lacks of settings.
    if (!empty($element['#third_party_settings']['blazy']['blazy'])) {
      self::thirdPartyField($variables);
    }

    // 2. Hence Blazy is the formatter, has its settings.
    if ($blazies && !$blazies->is('grid')) {
      BlazyAttribute::container($variables['attributes'], $settings);
    }
  }

  /**
   * Overrides variables for file-video.html.twig templates.
   */
  public static function fileVideo(array &$variables): void {
    $attributes = &$variables['attributes'];
    if ($files = $variables['files']) {
      $use_dataset = empty($attributes['data-b-undata']);

      if ($use_dataset) {
        $attributes->addClass(['b-lazy']);

        foreach ($files as $file) {
          $source_attributes = &$file['source_attributes'];
          $source_attributes->setAttribute('data-src', $source_attributes['src']->value());
          $source_attributes->setAttribute('src', '');
        }
      }

      // Adds a poster image if so configured.
      if ($blazy = ($files[0]['blazy'] ?? FALSE)) {
        if ($blazy->get('image.uri')) {
          $settings = $blazy->storage();
          $blazies = $settings['blazies'];

          if ($url = $blazies->get('image.url')) {
            if (!$blazies->get('use.loader') && $use_dataset) {
              $blazies->set('use.loader', TRUE);
            }
            $blazies->set('is.dimensions', TRUE);
            $attributes->setAttribute('poster', $url);
          }

          if ($blazies->is('lightbox') && $blazies->is('richbox')) {
            $attributes->setAttribute('autoplay', TRUE);
          }
        }
      }

      $attrs = ['data-b-lazy', 'data-b-undata'];
      $attributes->addClass(['media__element']);
      $attributes->removeAttribute($attrs);
    }
  }

  /**
   * Overrides variables for responsive-image.html.twig templates.
   */
  public static function responsiveImage(array &$variables): void {
    $image = &$variables['img_element'];
    $attributes = &$variables['attributes'];
    $placeholder = $attributes['data-b-placeholder'] ?? Placeholder::DATA;

    // Bail out if a noscript is requested.
    // @todo figure out to not even enter this method, yet not break ratio, etc.
    if (!isset($attributes['data-b-noscript'])) {
      // Modifies <picture> [data-srcset] attributes on <source> elements.
      if (!$variables['output_image_tag']) {
        /** @var \Drupal\Core\Template\Attribute $source */
        if ($sources = ($variables['sources'] ?? [])) {
          foreach ((array) $sources as &$source) {
            $source->setAttribute('data-srcset', $source['srcset']->value());
            $source->setAttribute('srcset', '');
          }
        }

        // Prevents invalid IMG tag when one pixel placeholder is disabled.
        $image['#uri'] = $placeholder;
        $image['#srcset'] = '';

        // Cleans up the no-longer relevant attributes for controlling element.
        unset($attributes['data-srcset'], $image['#attributes']['data-srcset']);
      }
      else {
        // Modifies <img> element attributes.
        $image['#attributes']['data-srcset'] = $attributes['srcset']->value();
        $image['#attributes']['srcset'] = '';
      }

      // Prioritized custom Placeholder ('/blank.svg') to fix for Views rewrite
      // results to override Responsive image `data:image` which causes 404.
      if ($ui = $attributes['data-b-ui'] ?? NULL) {
        $image['#uri'] = $ui;
      }
      // Prevents double-downloading the fallback image, enforced since 2.10, to
      // allow having non `data:image` as fallback image.
      else {
        $image['#uri'] = $placeholder;
      }

      // More shared-with-image attributes are set at BlazyAttribute::image().
      $image['#attributes']['class'][] = 'b-responsive';
    }

    // Cleans up the no-longer needed flags:
    foreach (['lazy', 'noscript', 'placeholder', 'ui'] as $key) {
      unset($attributes['data-b-' . $key], $image['#attributes']['data-b-' . $key]);
    }
  }

  /**
   * Overrides variables for media-oembed-iframe.html.twig templates.
   */
  public static function mediaOembedIframe(array &$variables): void {
    $request = Path::request();
    // Without internet, this may be empty, bail out.
    if (empty($variables['media']) || !$request) {
      return;
    }

    // Only needed to autoplay video, and make responsive iframe.
    try {
      // Blazy formatters with oEmbed provide contextual params to the query.
      $is_blazy = $request->query->getInt('blazy');
      $is_autoplay = $request->query->getInt('autoplay');
      $url = $request->query->get('url');

      // Only replace url if it is required by Blazy.
      if ($url && $is_blazy == 1) {
        // Load iframe string as a DOMDocument as alternative to regex.
        $dom = Html::load($variables['media']);
        $iframes = $dom->getElementsByTagName('iframe');

        // Replace old oEmbed url with autoplay support, and save the DOM.
        if ($iframes->length > 0 && $iframe = $iframes->item(0)) {
          // Autoplay url suitable for lightboxes, or custom video trigger.
          $embed_url = $iframe->getAttribute('src');

          // Only replace if autoplay == 1 for Image to iframe, or lightboxes.
          if ($is_autoplay == 1 && $embed_url) {
            $autoplay_url = Blazy::autoplay($embed_url);
            $iframe->setAttribute('src', $autoplay_url);
          }

          // Make responsive iframe with/ without autoplay.
          // The following ensures iframe does not shrink due to its attributes.
          $iframe->setAttribute('height', '100%');
          $iframe->setAttribute('width', '100%');

          $dom->getElementsByTagName('body')
            ->item(0)
            ->setAttribute('class', 'is-b-oembed');

          $variables['media'] = $dom->saveHTML();
        }
      }
    }
    catch (\Exception $ignore) {
      // Do nothing, likely local work without internet, or the site is down.
      // No need to be chatty on this.
    }
  }

  /**
   * Overrides variables for field.html.twig templates.
   */
  private static function thirdPartyField(array &$variables): void {
    $element = $variables['element'];
    $settings = $element['#blazy'] ?? [];

    Blazy::verify($settings);
    $blazies = $settings['blazies'];
    // @todo re-check at CKEditor.
    $is_undata = $blazies->is('undata');

    // @todo remove.
    $third_party = $element['#third_party_settings'] ?? [];
    $blazies->set('field.third_party', $third_party, TRUE);

    foreach ($variables['items'] as &$item) {
      if (empty($item['content'])) {
        continue;
      }

      $key = isset($item['content']['#attributes'])
        ? '#attributes' : '#item_attributes';

      if (isset($item['content'][$key])) {
        $item_attributes = &$item['content'][$key];
        $item_attributes['data-b-lazy'] = TRUE;

        if ($is_undata) {
          $item_attributes['data-b-undata'] = TRUE;
        }
      }
    }

    // Attaches Blazy libraries here since Blazy is not the formatter.
    Blazy::attach($variables, $settings);
  }

}
