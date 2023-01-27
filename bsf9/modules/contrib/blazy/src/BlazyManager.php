<?php

namespace Drupal\blazy;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Template\Attribute;
use Drupal\blazy\Theme\BlazyAttribute;
use Drupal\blazy\Cache\BlazyCache;
use Drupal\blazy\Theme\Lightbox;
use Drupal\blazy\Utility\CheckItem;

/**
 * Implements a public facing blazy manager.
 *
 * A few modules re-use this: GridStack, Mason, Slick...
 */
class BlazyManager extends BlazyManagerBase implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRenderBlazy', 'preRenderBuild'];
  }

  /**
   * Returns the enforced rich media content, or media using theme_blazy().
   *
   * @param array $build
   *   The array containing: item, content, settings, or optional captions.
   * @param int $delta
   *   The optional delta.
   *
   * @return array
   *   The alterable and renderable array of enforced content, or theme_blazy().
   *
   * @todo remove some $settings after sub-modules.
   */
  public function getBlazy(array $build = [], $delta = -1) {
    foreach (BlazyDefault::themeProperties() as $key) {
      $build[$key] = $build[$key] ?? [];
    }

    $settings = &$build['settings'];
    $settings += BlazyDefault::itemSettings();
    $item = $build['item'];

    CheckItem::essentials($settings, $item, $delta);

    // Prevents double checks.
    // @todo re-check for dup thumbnails without a reset here, see #3278525.
    $blazies = $settings['blazies']->reset($settings);
    $blazies->set('is.api', TRUE);

    // Respects content not handled by theme_blazy(), but passed through.
    // Yet allows rich contents which might still be processed by theme_blazy().
    $content = !$blazies->get('image.uri') ? $build['content'] : [
      '#theme'       => 'blazy',
      '#delta'       => $blazies->get('delta'),
      '#item'        => $item,
      '#image_style' => $settings['image_style'],
      '#build'       => $build,
      '#pre_render'  => [[$this, 'preRenderBlazy']],
    ];

    $this->moduleHandler->alter('blazy', $content, $settings);
    return $content;
  }

  /**
   * Builds the Blazy image as a structured array ready for ::renderer().
   *
   * @param array $element
   *   The pre-rendered element.
   *
   * @return array
   *   The renderable array of pre-rendered element.
   */
  public function preRenderBlazy(array $element) {
    $build = $element['#build'];
    unset($element['#build']);

    // Prepare the main image.
    $this->prepareBlazy($element, $build);

    // Fetch the newly modified settings.
    $settings = $element['#settings'];
    $blazies = $settings['blazies'];
    $url = $blazies->get('entity.url');

    if ($blazies->get('switch') == 'content' && $url) {
      $element['#url'] = $url;
    }
    elseif ($blazies->get('lightbox.name')) {
      Lightbox::build($element);
    }

    return $element;
  }

  /**
   * Returns the contents using theme_field(), or theme_item_list().
   *
   * Blazy outputs can be formatted using either flat list via theme_field(), or
   * a grid of Field items or Views rows via theme_item_list().
   *
   * @param array $build
   *   The array containing: settings, children elements, or optional items.
   *
   * @return array
   *   The alterable and renderable array of contents.
   */
  public function build(array $build = []) {
    $settings = &$build['settings'];
    Blazy::verify($settings);

    $blazies = $settings['blazies'];

    // This #pre_render doesn't work if called from Views results, hence the
    // output is split either as theme_field() or theme_item_list().
    if ($blazies->is('grid')) {
      // Take over theme_field() with a theme_item_list(), if so configured.
      // The reason: this is not only fed by field items, but also Views rows.
      $content = [
        '#build'      => $build,
        '#pre_render' => [[$this, 'preRenderBuild']],
      ];

      // Yet allows theme_field(), if so required, such as for linked_field.
      $build = $blazies->get('use.theme_field') ? [$content] : $content;
    }
    else {
      // If not a grid, pass items as regular index children to theme_field().
      $settings = $this->getSettings($build);
      Blazy::verify($settings);

      // Runs after ::getSettings.
      $this->toElementChildren($build);
      $build['#blazy'] = $settings;
      $this->setAttachments($build, $settings);
    }

    $this->moduleHandler->alter('blazy_build', $build, $settings);
    return $build;
  }

  /**
   * Builds the Blazy outputs as a structured array ready for ::renderer().
   */
  public function preRenderBuild(array $element): array {
    $build = $element['#build'];
    unset($element['#build']);

    // Checks if we got some signaled attributes.
    $attributes = $element['#theme_wrappers']['container']['#attributes'] ?? $element['#attributes'] ?? [];
    $settings   = $this->getSettings($build);

    // Runs after ::getSettings.
    $this->toElementChildren($build);

    // Take over elements for a grid display as this is all we need, learned
    // from the issues such as: #2945524, or product variations.
    // We'll selectively pass or work out $attributes not so far below.
    $element = $this->toGrid($build, $settings);
    $this->setAttachments($element, $settings);

    if ($attributes) {
      // Signals other modules if they want to use it.
      // Cannot merge it into BlazyGrid (wrapper_)attributes, done as grid.
      // Use case: Product variations, best served by ElevateZoom Plus.
      if (isset($element['#ajax_replace_class'])) {
        $element['#container_attributes'] = $attributes;
      }
      else {
        // Use case: VIS, can be blended with UL element safely down here.
        // The $attributes is merged with self::toGrid() ones here.
        $element['#attributes'] = NestedArray::mergeDeep($element['#attributes'], $attributes);
      }
    }

    return $element;
  }

  /**
   * Build captions for both old image, or media entity.
   */
  protected function buildCaption(array $captions, array $settings, $id = 'blazy') {
    $blazies = $settings['blazies'];
    $content = [];

    foreach ($captions as $key => $caption_content) {
      if ($caption_content) {
        $content[$key]['content'] = $caption_content;
        $content[$key]['tag'] = strpos($key, 'title') !== FALSE ? 'h2' : 'div';
        $class = $key == 'alt' ? 'description' : str_replace('field_', '', $key);

        $attrs = new Attribute();
        $attrs->addClass($id . '__caption--' . str_replace('_', '-', $class));
        $content[$key]['attributes'] = $attrs;
      }
    }

    // Figcaption is more relevant for core filter captions under Figure.
    $tag = $blazies->is('figcaption') ? 'figcaption' : 'div';

    return $content ? ['inline' => $content, 'tag' => $tag] : [];
  }

  /**
   * Build out (rich media) content.
   */
  private function buildContent(array &$element, array &$build) {
    $settings = &$build['settings'];
    $blazies = $settings['blazies'];

    if (empty($build['content'])) {
      return;
    }

    // Prevents complication for now, such as lightbox for Facebook, etc.
    // Either makes no sense, or not currently supported without extra legs.
    // Original formatter settings can still be accessed via content variable.
    $blazies->set('placeholder', [])
      ->set('is.bg', FALSE)
      ->set('use.loader', FALSE);

    // $settings = array_merge($settings, BlazyDefault::richSettings());
    // Supports HTML content for lightboxes as long as having image trigger.
    // Type rich to not conflict with Image rendered by its formatter option.
    $supported = $blazies->is('richbox') ?: $settings['_richbox'] ?? FALSE;
    $rich = $blazies->get('media.type') == 'rich' && $supported;
    $litebox = $blazies->is('lightbox');
    $blazy = ($build['content'][0]['#settings'] ?? NULL);

    if ($rich && $litebox && is_object($blazy)) {
      if ($blazies->is('hires', !empty($settings['image']))) {
        // Overrides the overriden settings with original formatter settings.
        $settings = array_merge($settings, $blazy->storage());
        $element['#lightbox_html'] = $build['content'];
        $build['content'] = [];
      }
    }
  }

  /**
   * Build out (Responsive) image.
   *
   * Since 2.9, many were moved into BlazyTheme to support custom work better.
   */
  private function buildMedia(array &$element, array &$build): void {
    $item = $build['item'];
    $settings = &$build['settings'];
    $blazies = $settings['blazies'];

    // (Responsive) image with item attributes, might be RDF.
    $item_attributes = empty($build['item_attributes'])
      ? []
      : BlazyAttribute::sanitize($build['item_attributes']);

    // Extract field item attributes for the theme function, and unset them
    // from the $item so that the field template does not re-render them.
    if ($item && isset($item->_attributes)) {
      $item_attributes += $item->_attributes;
      unset($item->_attributes);
    }

    // Responsive image integration, with/o CSS background so to work with.
    $resimage = $blazies->get('resimage');
    if ($resimage && $caches = $resimage['caches'] ?? []) {
      $element['#cache']['tags'] = $caches;
    }

    // Provides caches for regular image, with/o CSS background.
    if (!$blazies->get('resimage.id')) {
      if ($caches = BlazyCache::file($settings)) {
        $element['#cache']['max-age'] = -1;
        foreach ($caches as $key => $cache) {
          $element['#cache'][$key] = $cache;
        }
      }
    }

    // Pass non-rich-media elements to theme_blazy().
    $element['#item_attributes'] = $item_attributes;
  }

  /**
   * Prepares Blazy settings.
   *
   * Supports galeries if provided, updates $settings.
   * Cases: Blazy within Views gallery, or references without direct image.
   * Views may flatten out the array, bail out.
   * What we do here is extract the formatter settings from the first found
   * image and pass its settings to this container so that Blazy Grid which
   * lacks of settings may know if it should load/ display a lightbox, etc.
   * Lightbox should work without `Use field template` checked.
   */
  private function getSettings(array &$build) {
    $settings = $build['settings'] ?? [];
    $blazies = $settings['blazies'] ?? NULL;

    if ($blazies && $data = $blazies->get('first.data')) {
      if (is_array($data)) {
        $this->isBlazy($settings, $data);
      }
    }

    return $settings;
  }

  /**
   * Prepares the Blazy output as a structured array ready for ::renderer().
   *
   * @param array $element
   *   The renderable array being modified.
   * @param array $build
   *   The array of information containing the required Image or File item
   *   object, settings, optional container attributes.
   */
  private function prepareBlazy(array &$element, array $build) {
    $item     = $build['item'] ?? NULL;
    $settings = &$build['settings'];
    $blazies  = $settings['blazies'];

    foreach (BlazyDefault::themeAttributes() as $key) {
      $key = $key . '_attributes';
      $build[$key] = $build[$key] ?? [];
    }

    // Blazy has these 3 attributes, yet provides optional ones far below.
    // Sanitize potential user-defined attributes such as from BlazyFilter.
    // Skip attributes via $item, or by module, as they are not user-defined.
    $attributes = &$build['attributes'];

    // Initial feature checks, URI, delta, media features, etc.
    Blazy::prepare($settings, $item);

    // Build thumbnail and optional placeholder based on thumbnail.
    // Prepare image URL and its dimensions, including for rich-media content,
    // such as for local video poster image if a poster URI is provided.
    Blazy::prepared($attributes, $settings, $item);

    // Only process (Responsive) image/ video if no rich-media are provided.
    $this->buildContent($element, $build);
    if (empty($build['content'])) {
      $this->buildMedia($element, $build);
    }

    // Provides extra attributes as needed, excluding url, item, done above.
    // Was planned to replace sub-module item markups if similarity is found for
    // theme_gridstack_box(), theme_slick_slide(), etc. Likely for Blazy 3.x+.
    foreach (['caption', 'media', 'wrapper'] as $key) {
      $element["#$key" . '_attributes'] = empty($build[$key . '_attributes'])
        ? [] : BlazyAttribute::sanitize($build[$key . '_attributes']);
    }

    // Provides captions, if so configured.
    $id = $blazies->get('item.id', 'blazy');
    $content = $build['captions'] ?? '';
    if ($content && ($captions = $this->buildCaption($content, $settings, $id))) {
      $element['#captions'] = $captions;
      $element['#caption_attributes']['class'][] = $id . '__caption';
    }

    // Pass common elements to theme_blazy().
    $element['#attributes']     = $attributes;
    $element['#settings']       = $settings;
    $element['#url_attributes'] = $build['url_attributes'];

    // Preparing Blazy to replace other blazy-related content/ item markups.
    // Composing or layering is crucial for mixed media (icon over CTA or text
    // or lightbox links or iframe over image or CSS background over noscript
    // which cannot be simply dumped as array without elaborate arrangements).
    foreach (['content', 'icon', 'overlay', 'preface', 'postscript'] as $key) {
      $element["#$key"] = empty($element["#$key"]) ? $build[$key] : NestedArray::mergeDeep($element["#$key"], $build[$key]);
    }
  }

  /**
   * Prepares Blazy outputs, extract items as indices.
   *
   * If children are grouped within items property, reset to indexed keys.
   * Blazy comes late to the party after sub-modules decided what they want
   * where items may be stored as direct indices, or put into items property.
   * Actually the same issue happens at core where contents may be indexed or
   * grouped. Meaning not a problem at all, only a problem for consistency.
   */
  private function toElementChildren(array &$build): void {
    $build = $build['items'] ?? $build;
    unset($build['items'], $build['settings']);
  }

}
