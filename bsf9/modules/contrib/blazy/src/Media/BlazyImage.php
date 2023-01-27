<?php

namespace Drupal\blazy\Media;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\image\Plugin\Field\FieldType\ImageItem;
use Drupal\media\MediaInterface;
use Drupal\blazy\Blazy;
use Drupal\blazy\Utility\Path;

/**
 * Provides image-related methods.
 *
 * @todo recap similiraties and make them plugins.
 */
class BlazyImage {

  /**
   * Checks if the image style contains crop in the effect name.
   *
   * @var array
   */
  private static $crop;

  /**
   * Checks if image dimensions are set.
   *
   * @var array
   */
  private static $isCropSet;

  /**
   * The image style ID.
   *
   * @var array
   */
  private static $styleId;

  /**
   * Prepares CSS background image.
   */
  public static function background(array $settings, $style = NULL) {
    // @tbd replace src with URL before 3.x, or keep it.
    return [
      'src' => self::url($settings, $style),
      'ratio' => self::ratio($settings),
    ];
  }

  /**
   * Returns the image style if it contains crop effect.
   *
   * @param object $style
   *   The image style to check for.
   *
   * @return object
   *   Returns the image style instance if it contains crop effect, else NULL.
   */
  public static function getCrop($style): ?object {
    $id = $style->id();

    if (!isset(static::$crop[$id])) {
      $output = NULL;

      foreach ($style->getEffects() as $effect) {
        if (strpos($effect->getPluginId(), 'crop') !== FALSE) {
          $output = $style;
          break;
        }
      }
      static::$crop[$id] = $output;
    }
    return static::$crop[$id];
  }

  /**
   * Sets dimensions once to reduce method calls, if image style contains crop.
   *
   * @param array $settings
   *   The settings being modified.
   * @param object $style
   *   The image style to check for crp effect.
   */
  public static function cropDimensions(array &$settings, $style): void {
    $id = $style->id();

    if (!isset(static::$isCropSet[$id])) {
      // If image style contains crop, sets dimension once, and let all inherit.
      if ($crop = self::getCrop($style)) {
        $blazies = $settings['blazies'];
        $settings = array_merge($settings, self::transformDimensions($crop, $settings, TRUE));

        $data = ['width' => $settings['width'], 'height' => $settings['height']];
        $ratio = self::ratio($data);

        // Informs individual images that dimensions are already set once.
        $blazies->set('image', $data, TRUE)
          ->set('image.ratio', $ratio)
          ->set('is.dimensions', TRUE);
      }

      static::$isCropSet[$id] = TRUE;
    }
  }

  /**
   * Provides original unstyled image dimensions based on the given image item.
   *
   * This one is original image, not styled like self:transformDimensions().
   * Sources: formatters, filters or any hard-coded unmanaged files like VEF.
   */
  public static function dimensions(array &$settings, $item = NULL, $initial = FALSE): void {
    $blazies = $settings['blazies'];
    $_width  = $initial ? '_width' : 'width';
    $_height = $initial ? '_height' : 'height';
    $_uri    = $initial ? '_uri' : 'uri';
    $width   = $settings[$_width] ?? NULL;
    $height  = $settings[$_height] ?? NULL;
    $uri     = $settings[$_uri] ?? '';

    if (empty($height) && $item) {
      $width = $item->width ?? NULL;
      $height = $item->height ?? NULL;
    }

    // Only applies when Image style is empty, no file API, no $item,
    // with unmanaged VEF/ WYSIWG/ filter image without image_style.
    if ($uri && empty($settings['image_style']) && empty($height)) {
      $abs = empty($settings['uri_root']) ? $uri : $settings['uri_root'];
      // Must be valid URI, or web-accessible url, not: /modules|themes/...
      if (!BlazyFile::isValidUri($abs) && mb_substr($abs, 0, 1) == '/') {
        if ($request = Path::requestStack()) {
          $abs = $request->getCurrentRequest()->getSchemeAndHttpHost() . $abs;
        }
      }

      // Prevents 404 warning when video thumbnail missing for a reason.
      if ($data = @getimagesize($abs)) {
        [$width, $height] = $data;
      }
    }

    // Sometimes they are string, cast them integer to reduce JS logic.
    $settings[$_width] = $width;
    $settings[$_height] = $height;

    self::toInt($settings, $_width, $_height);

    // Defines original dimensions.
    $data = ['width' => $settings[$_width], 'height' => $settings[$_height]];
    $ratio = self::ratio($data);
    $blazies->set('image.original', $data, TRUE)
      ->set('image.original.ratio', $ratio);

    // In case `image_style` is not provided.
    if ($initial) {
      $blazies->set('image', $data, TRUE)
        ->set('image.ratio', $ratio);
    }
  }

  /**
   * Returns fake image item based on the given $settings.
   *
   * @todo use blazies after migration.
   */
  public static function fake(array $settings) {
    $item = new \stdClass();
    foreach (['uri', 'width', 'height', 'target_id', 'alt', 'title'] as $key) {
      if (isset($settings[$key])) {
        $item->{$key} = $settings[$key];
      }
    }
    return $item;
  }

  /**
   * Returns the image item out of File entity, ER, etc., or just $settings.
   *
   * @param object $object
   *   The optional Media, File entity, or ER, etc. to get image item from.
   * @param array $settings
   *   The optional settings.
   *
   * @return mixed
   *   The object of image item, or NULL.
   *
   * @todo simplify this, like everything else. An obvious confusion here.
   * @todo return image item directly without settings.
   */
  public static function fromAny($object = NULL, array &$settings = []): ?object {
    // @todo remove check at 3.x after sub-modules and VEF removed.
    Blazy::verify($settings);
    $blazies = $settings['blazies'];

    $output = $uri = NULL;

    // If Media entity, we must have a File entity, and likely ImageItem.
    if ($object instanceof MediaInterface) {
      $entity = $object;
    }
    else {
      // Extracts File entity from any object or settings, if applicable.
      // Node, EntityReferenceRevisionsItem, etc.
      $entity = BlazyFile::item($object, $settings);

      // Called by BlazyFilter file upload and legacy BlazyViewsFieldFile.
      if (BlazyFile::isFile($entity)
        && $factory = Blazy::service('image.factory')) {
        $uri = $entity->getFileUri();
        if ($image = $factory->get($uri)) {
          $output = self::fakeWithdata($entity, $image);
        }
      }
    }

    // Called by formatters.
    if (empty($output)) {
      $options = [
        'entity' => $entity,
        'source' => $entity == $object ? NULL : $object,
        'settings' => $settings,
      ];

      // We may have a Media entity, etc.
      $output = self::fromContent($options);
    }

    // @todo remove after sub-modules, require by thumbnails till updated.
    $uri = $settings['uri'] = $uri ?: BlazyFile::uri($output, $settings);
    $blazies->set('image.uri', $uri);

    return $output;
  }

  /**
   * Returns TRUE if an ImageItem.
   */
  public static function isImage($item): bool {
    return $item instanceof ImageItem;
  }

  /**
   * Returns the image item from any sources, if available.
   *
   * PHP 7.2 accepts object. D8 >= PHP 7.3. Not good for D7 backport.
   */
  public static function item($item = NULL, array $options = [], $name = NULL): ?object {
    return self::isImage($item) ? $item : self::fromContent($options, $name);
  }

  /**
   * Returns the image item from any sources, if available.
   *
   * This block is a bit scary yet it is a more organized way to extract Image
   * item from various sources in tandem with custom settings.image previously
   * scattered with if-else. This has saved more than 60 lines, and two methods:
   * ::fromMedia(), already gone, and ::fromField(), to be gone. Can be better.
   */
  public static function fromContent(array $options = [], $name = NULL): ?object {
    $settings = $options['settings'] ?? [];
    $blazies  = $settings['blazies'] ?? NULL;
    $poster   = $settings['image'] ?? NULL;
    $name     = $name ?: $poster;

    // If poster is not defined, use the source_field or thumbnail property.
    // Title is NULL from thumbnail, likely core bug, so use source.
    if ($blazies && !$name && $source = $blazies->get('media.source')) {
      $name = $source == 'image' ? $blazies->get('media.source_field') : 'thumbnail';
    }

    $func = function ($key, $property) use ($options) {
      $object = ($options[$key] ?? NULL);
      if ($object instanceof ContentEntityInterface
        && $object->hasField($property)) {
        $item = $object->get($property)->first();
        $valid = self::isImage($item);

        // Media embedded inside Paragraph item as defined by settings.image,
        // basically drilling down nested entities here to find the gold.
        if (!$valid && $item && $entity = ($item->entity ?? NULL)) {
          if ($entity instanceof ContentEntityInterface
            && $entity->hasField('thumbnail')) {
            $item = $entity->get('thumbnail')->first();
            $valid = self::isImage($item);
          }
        }

        // Specific for Remote video, it has meaningful label from OEmbed, OOTB.
        if ($valid && trim($item->title ?? '') == '') {
          $item->title = $object->label();
        }
        return $valid ? $item : NULL;
      }
      return NULL;
    };

    // \Drupal\paragraphs\Entity\Paragraph, Media, Node, etc.
    $item = $func('entity', $name) ?: $func('source', $name);
    $item = $name ? $item : NULL;
    if (!$item) {
      $item = $func('entity', 'thumbnail') ?: $func('source', 'thumbnail');
    }

    return $item;
  }

  /**
   * Disable image style if so configured.
   *
   * Extensions without image styles: animated GIF, APNG, SVG, etc.
   */
  public static function isUnstyled($uri, array $settings, $ext = NULL): bool {
    $blazies = $settings['blazies'];
    $ext = $ext ?: pathinfo($uri, PATHINFO_EXTENSION);
    $extensions = ['svg'];

    // If we have added extensions.
    if ($unstyles = $blazies->get('ui.unstyled_extensions', [])) {
      $extensions = array_merge($extensions,
      array_map('trim', explode(' ', mb_strtolower($unstyles))));
      $extensions = array_unique($extensions);
    }

    return $ext && in_array($ext, $extensions);
  }

  /**
   * Checks if we have image item.
   *
   * Both ImageItem and fake stdClass are valid, no problem.
   */
  public static function isValidItem($item): bool {
    $item = is_array($item) ? ($item['item'] ?? NULL) : $item;
    return is_object($item) && (isset($item->uri) || isset($item->target_id));
  }

  /**
   * Prepares URLs, placeholder, and dimensions for an individual image.
   *
   * Respects a few scenarios:
   * 1. Blazy Filter or unmanaged file with/ without valid URI.
   * 2. Hand-coded image_url with/ without valid URI.
   * 3. Respects first_uri without image_url such as colorbox/zoom-like.
   * 4. File API via field formatters or Views fields/ styles with valid URI.
   * If we have a valid URI, provides the correct image URL.
   * Otherwise leave it as is, likely hotlinking to external/ sister sites.
   * Hence URI validity is not crucial in regards to anything but #4.
   * The image will fail silently at any rate given non-expected URI.
   *
   * @param array $settings
   *   The given settings being modified.
   * @param object $item
   *   The image item.
   *
   * @requires CheckItem::unstyled()
   */
  public static function prepare(array &$settings, $item = NULL): void {
    $blazies = &$settings['blazies'];
    $style   = $blazies->is('unstyled') ? NULL : $blazies->get('image.style');

    // Might be called from Views without Blazy formatter, like Image formatter.
    // Since Blazy:2.9, image style entity is loaded once at container level,
    // but might still be needed for adopted Image formatter by a Views style.
    // @todo since done at container, it might also truble the unstyled per URI.
    if (!$style && !empty($settings['image_style'])) {
      self::styles($settings);
      $style = $blazies->get('image.style');
    }

    // BlazyFilter, or image style with crop, may already set these.
    self::dimensions($settings, $item, FALSE);

    // Provides image url based on the given settings.
    if ($style) {
      $blazies->set('cache.tags', $style->getCacheTags(), TRUE);

      // Only re-calculate dimensions if not cropped, nor already set.
      if (!$blazies->is('dimensions')
        && empty($settings['responsive_image_style'])) {
        $settings = array_merge($settings, self::transformDimensions($style, $settings));
      }
    }

    // Currently doesn't affect option.ratio, a failsafe for BG, else collapsed.
    $data = ['width' => $settings['width'], 'height' => $settings['height']];
    $url = self::url($settings, $style);
    $ratio = self::ratio($data);

    $blazies->set('image', $data, TRUE)
      ->set('image.ratio', $ratio)
      ->set('image.url', $url);
  }

  /**
   * Provides a computed image ratio aka fluid ratio.
   *
   * Addresses multi-image-style Responsive image or, plain old one.
   * A failsafe for BG, else collapsed.
   *
   * @todo decide if to provide NULL or 0 instead.
   */
  public static function ratio(array $settings) {
    $no_dims = empty($settings['height']) || empty($settings['width']);
    return $no_dims ? 100 : round((($settings['height'] / $settings['width']) * 100), 2);
  }

  /**
   * Checks for Image styles.
   *
   * Specific for lightbox, it can be (Responsive) image, but not here.
   *
   * @param array $settings
   *   The modified settings.
   * @param bool $multiple
   *   A flag for various Image styles: Blazy Filter, etc., old GridStack.
   *   While most field formatters can only have one image style per field.
   */
  public static function styles(array &$settings, $multiple = FALSE): void {
    $blazies = $settings['blazies'];
    if ($blazy = Blazy::service('blazy.manager')) {
      foreach (['box', 'box_media', 'image', 'thumbnail'] as $key) {
        if (!$blazies->get($key . '.style') || $multiple) {
          if ($_style = ($settings[$key . '_style'] ?? '')) {
            if ($entity = $blazy->entityLoad($_style, 'image_style')) {
              $blazies->set($key . '.style', $entity)
                ->set($key . '.id', $entity->id());
            }
          }
        }
      }
    }
  }

  /**
   * Returns the thumbnail image using theme_image(), or theme_image_style().
   */
  public static function thumbnail(array $settings, $item = NULL): array {
    if ($uri = BlazyFile::uri($item, $settings)) {
      $external = UrlHelper::isExternal($uri);
      $style = $settings['thumbnail_style'] ?? NULL;

      return [
        '#theme'      => $external ? 'image' : 'image_style',
        '#style_name' => $style ?: 'thumbnail',
        '#uri'        => $uri,
        '#item'       => $item,
        '#alt'        => self::isImage($item) ? $item->getValue()['alt'] : '',
      ];
    }
    return [];
  }

  /**
   * A wrapper for ImageStyle::transformDimensions().
   *
   * @param object $style
   *   The given image style.
   * @param array $data
   *   The data settings: _width, _height, _uri, width, height, and uri.
   *   The `_` prefix identifies it as the initial call at container level.
   * @param bool $initial
   *   Whether particularly transforms once for all, or individually.
   */
  public static function transformDimensions($style, array $data, $initial = FALSE): array {
    $_uri = $initial ? '_uri' : 'uri';
    $uri  = $data[$_uri] ?? '';
    $key  = hash('md2', ($style->id() . $uri . $initial));

    if (!isset(static::$styleId[$key])) {
      $_width  = $initial ? '_width' : 'width';
      $_height = $initial ? '_height' : 'height';
      $width   = $data[$_width] ?? NULL;
      $height  = $data[$_height] ?? NULL;
      $dim     = ['width' => $width, 'height' => $height];

      // Funnily $uri is ignored at all core image effects.
      $style->transformDimensions($dim, $uri);

      // Sometimes they are string, cast them integer to reduce JS logic.
      self::toInt($dim, 'width', 'height');

      // Keys here are hard-coded, so to be inherited by children as intended.
      // The underscore prefix is to identify the source/ original unstyled
      // image properties, not related to the final output printed here.
      // See self::dimensions().
      // @todo re-check if the container needs image style dimensions.
      static::$styleId[$key] = [
        'width' => $dim['width'],
        'height' => $dim['height'],
      ];
    }
    return static::$styleId[$key];
  }

  /**
   * Returns image URL with an optional image style.
   *
   * Addressed various sources:
   * - URL which should not be styled: animated gif, apng, svg, etc.
   * - UGC image URL, with likely invalid URI due to hard-coded markdown, etc.
   * - Responsive image vs. regular image style.
   *
   * @requires \Drupal\blazy\Blazy::prepare()
   *
   * @see self::prepare()
   * @see self::background()
   * @see BlazyResponsiveImage::background()
   *
   * @todo remove fallbacks after another check, also settings after migration.
   */
  public static function url(array $settings, $style = NULL, $uri = NULL) {
    $blazies = $settings['blazies'];
    $uri     = $uri ?: $blazies->get('image.uri');
    $valid   = BlazyFile::isValidUri($uri);
    $styled  = $valid && !$blazies->is('unstyled');
    $style   = $styled ? $style : NULL;
    $url     = $settings['image_url'] ?? '';
    $url     = $url ?: $blazies->get('image.url');
    $options = ['url' => $url, 'sanitize' => $blazies->is('unsafe')];

    return BlazyFile::transformRelative($uri, $style, $options);
  }

  /**
   * Returns data to provide fake image item of file entity.
   */
  private static function fakeWithdata($file, $image): ?object {
    if ($settings = self::fromFactory($file, $image)) {
      if ($item = self::fake($settings)) {
        $item->entity = $file;
        /* @todo revert return ['item' => $item, 'settings' => $settings]; */
        return $item;
      }
    }
    return NULL;
  }

  /**
   * Returns image data via ImageFactory to provide fake image item.
   */
  private static function fromFactory($file, $image): array {
    /** @var \Drupal\file\Entity\File $file */
    [$type] = explode('/', $file->getMimeType(), 2);

    if ($type == 'image' && $image->isValid()) {
      return [
        'uri'       => $file->getFileUri(),
        'target_id' => $file->id(),
        'width'     => $image->getWidth(),
        'height'    => $image->getHeight(),
        'alt'       => $file->getFilename(),
        'title'     => $file->getFilename(),
        'type'      => 'image',
      ];
    }
    return [];
  }

  /**
   * Converts dimensions to integer unless empty.
   */
  private static function toInt(array &$settings, $width, $height): void {
    $settings[$width] = empty($settings[$width]) ? NULL : (int) $settings[$width];
    $settings[$height] = empty($settings[$height]) ? NULL : (int) $settings[$height];
  }

  /**
   * Extracts image from non-media entities for the main background/ stage.
   *
   * Main image can be separate image item from video thumbnail for highres.
   * Fallback to default thumbnail if any, which has no file API. This used to
   * be for non-media File Entity Reference at 1.x, things changed since then.
   * Some core methods during Blazy 1.x are now gone at 2.x.
   * Re-purposed for Paragraphs, Node, etc. which embeds Media or File.
   *
   * @param array $data
   *   The element array might contain item and settings.
   * @param object $entity
   *   The file entity or entityreference which might have image item.
   * @param string $name
   *   The field name to extract image item.
   *
   * @see \Drupal\blazy\Field\BlazyEntityMediaBase::buildElement
   *
   * Called by SplideVanillaWithNavTrait till Splide removes it for ::build().
   * This used to be for File entity (non-media).
   * Extracts image item from non-media, such as Paragraphs, Node, etc.
   * @todo re-check, some File core methods are gone at Blazy 2.x.
   * @todo deprecate and remove for ::fromAny(), and only after sub-modules.
   */
  public static function fromField(array &$data, $entity, $name): void {
    $settings = &$data['settings'];

    // The actual video thumbnail has already been downloaded earlier.
    // This fetches the highres image if provided and available.
    // With a mix of image and video, image is not always there.
    /** @var \Drupal\file\Plugin\Field\FieldType\FileFieldItemList $field */
    if (isset($entity->{$name}) && $field = $entity->get($name)) {
      $values = $field->getValue();
      $valid = $values[0]['target_id'] ?? FALSE;

      // Do not proceed if it is a Media entity video. This means File here.
      if ($valid && $exist = method_exists($field, 'referencedEntities')) {
        // The reference can be File or Media.
        // If image, even if multi-value, we can only have one stage per slide.
        /** @var \Drupal\file\Entity\File $reference */
        /** @var Drupal\media\MediaInterface $reference */
        $reference = $field->referencedEntities()[0] ?? NULL;
        $ok = FALSE;
        $object = $field;

        if ($reference instanceof MediaInterface) {
          $object = $reference;
          BlazyMedia::prepare($data, $reference);
          $ok = !empty($data['item']);
        }

        // Pass it directly if a File.
        $object = BlazyFile::isFile($reference) ? $reference : $object;

        // Called by BlazyFilter and legacy File entity like Views file.
        // Also vanilla Splide for the main stage.
        if (!$ok && $result = self::fromAny($object, $settings)) {
          // $data = NestedArray::mergeDeep($data, $result);
          $data['item'] = $result;
        }
      }
    }
  }

}
