<?php

namespace Drupal\blazy\Media;

// @todo revert use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Url;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Image\ImageFactory;
use Drupal\media\IFrameUrlHelper;
use Drupal\media\MediaInterface;
use Drupal\media\OEmbed\ResourceFetcherInterface;
use Drupal\media\OEmbed\UrlResolverInterface;
use Drupal\blazy\BlazyManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides OEmbed integration.
 */
class BlazyOEmbed implements BlazyOEmbedInterface {

  /**
   * Core Media oEmbed url resolver.
   *
   * @var \Drupal\media\OEmbed\UrlResolverInterface
   */
  protected $urlResolver;

  /**
   * Core Media oEmbed resource fetcher.
   *
   * @var \Drupal\media\OEmbed\ResourceFetcherInterface
   */
  protected $resourceFetcher;

  /**
   * Core Media oEmbed iframe url helper.
   *
   * @var \Drupal\media\IFrameUrlHelper
   */
  protected $iframeUrlHelper;

  /**
   * The blazy manager service.
   *
   * @var \Drupal\blazy\BlazyManagerInterface
   */
  protected $blazyManager;

  /**
   * The Media oEmbed Resource.
   *
   * @var \Drupal\media\OEmbed\Resource[]
   */
  protected $resource;

  /**
   * The request service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * The image factory service.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * Constructs a BlazyManager object.
   *
   * @todo remove ::imageFactory (was for UGC), not used anywhere since 2.6.
   */
  public function __construct(RequestStack $request, ResourceFetcherInterface $resource_fetcher, UrlResolverInterface $url_resolver, IFrameUrlHelper $iframe_url_helper, ImageFactory $image_factory, BlazyManagerInterface $blazy_manager) {
    $this->request = $request;
    $this->resourceFetcher = $resource_fetcher;
    $this->urlResolver = $url_resolver;
    $this->iframeUrlHelper = $iframe_url_helper;
    // @todo remove before 3.x, no longer in use.
    $this->imageFactory = $image_factory;
    $this->blazyManager = $blazy_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('media.oembed.resource_fetcher'),
      $container->get('media.oembed.url_resolver'),
      $container->get('media.oembed.iframe_url_helper'),
      $container->get('image.factory'),
      $container->get('blazy.manager')
    );
  }

  /**
   * Returns the Media oEmbed resource fecther.
   */
  public function getResourceFetcher() {
    return $this->resourceFetcher;
  }

  /**
   * Returns the Media oEmbed url resolver fecthers.
   */
  public function getUrlResolver() {
    return $this->urlResolver;
  }

  /**
   * Returns the Media oEmbed url resolver fecthers.
   */
  public function getIframeUrlHelper() {
    return $this->iframeUrlHelper;
  }

  /**
   * Returns the image factory.
   *
   * @todo remove ::imageFactory (was for UGC), not used anywhere since 2.6.
   */
  public function imageFactory() {
    return $this->imageFactory;
  }

  /**
   * Returns the blazy manager.
   */
  public function blazyManager() {
    return $this->blazyManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getResource($input_url) {
    if (!isset($this->resource[hash('md2', $input_url)])) {
      $resource_url = $this->urlResolver->getResourceUrl($input_url, 0, 0);
      $this->resource[hash('md2', $input_url)] = $this->resourceFetcher->fetchResource($resource_url);
    }

    return $this->resource[hash('md2', $input_url)];
  }

  /**
   * {@inheritdoc}
   *
   * @todo should be at non-static BlazyMedia at 4.x, if too late for 3.x.
   */
  public function build(array &$build, $entity = NULL): void {
    // @todo remove old approach at 3.x after old VEF BlazyVideoTrait removed.
    if (!isset($build['settings'])) {
      $this->toEmbed($build);
      return;
    }

    // Extracts image item from Media, File entity, ER, FieldItemList, etc.
    $this->fromMediaOrAny($build, $entity);
  }

  /**
   * Checks the given input URL.
   */
  public function checkInputUrl(array &$settings): void {
    $blazies = $settings['blazies'];

    if ($input = $blazies->get('media.input_url')) {
      $input = UrlHelper::stripDangerousProtocols($input);

      // OEmbed Resource doesn't accept `/embed`, provides a conversion helper.
      if (strpos($input, 'youtube.com/embed') !== FALSE) {
        $search = '/youtube\.com\/embed\/([a-zA-Z0-9]+)/smi';
        $replace = "youtube.com/watch?v=$1";
        $input = preg_replace($search, $replace, $input);
      }
    }

    $blazies->set('media.input_url', $input);
  }

  /**
   * Returns external image item from resource for BlazyFilter or VEF.
   *
   * @todo remove settings after migration, and sub-modules.
   */
  private function getExternalImageItem(array &$settings): ?object {
    $blazies = $settings['blazies'];
    $input   = $blazies->get('media.input_url');
    $uri     = $settings['uri'] ?? NULL;
    $uri     = $uri ?: $blazies->get('image.uri');
    $height  = $settings['height'] ?? $blazies->get('image.height');
    $width   = $settings['width'] ?? $blazies->get('image.width');
    $title   = $blazies->get('media.label') ?: $blazies->get('image.title');
    $type    = $blazies->get('media.type', 'video');

    // Iframe URL may be valid, but not stored as a Media entity.
    if ($input && $resource = $this->getResource($input)) {
      $title = $resource->getTitle() ?: $title;
      $type = $resource->getType();

      // VEF has valid local URI, other hard-coded unmanaged files might not.
      if (!BlazyFile::isValidUri($uri)) {
        // All we have here is external images. URI validity is not crucial.
        if (!empty($resource->getThumbnailUrl())) {
          $uri = $resource->getThumbnailUrl()->getUri();
        }
      }

      // Respect hard-coded width and height since no UI for all these here.
      if (!$height) {
        $width = $resource->getThumbnailWidth() ?: $resource->getWidth();
        $height = $resource->getThumbnailHeight() ?: $resource->getHeight();
      }
    }

    // @todo remove settings.
    $settings['type'] = $type;
    $settings['uri'] = $uri;
    $blazies->set('media.label', $title)
      ->set('media.type', $type);

    // VEF has just URI, the rest are fetched from resource.
    $data = [
      'uri' => $uri,
      'width' => $width,
      'height' => $height,
      'alt' => $title,
      'title' => $title,
    ];

    return $uri ? BlazyImage::fake($data) : NULL;
  }

  /**
   * Temporary method to be compatible with old approach pre 2.10.
   *
   * @todo move it directly into ::build() after sub-modules.
   */
  private function fromMediaOrAny(array &$build, $entity = NULL): void {
    $settings = &$build['settings'];
    $blazies = $settings['blazies']->reset($settings);
    $valid = $entity instanceof MediaInterface;
    $stage = $settings['image'] ?? NULL;

    // Two designated types of $stage: MediaInterface and FileInterface.
    // Since 2.10, Main stage is usable as the main display of a Paragraphs,
    // only if the stage is a Media entity and Overlay is left empty. Basically
    // render the Media and replace its parent $entity. This way if it is a
    // video, Media switch will kick in as a Media player or simply an iframe.
    // Old behavior is intact if Overlay is provided as previously designed.
    // Before 2.10, the stage was always made an Image, and required Overlay
    // to have a video player or iframe on top of the stage as an Image.
    if (!$valid && $entity && $stage && empty($settings['overlay'])) {
      if (isset($entity->{$stage}) && $reference = $entity->get($stage)->first()) {
        if ($reference instanceof EntityReferenceItem) {
          $object = $reference->entity;
          if ($object instanceof MediaInterface) {
            $entity = $object;
            $valid = TRUE;
          }
        }
      }
    }

    /** @var \Drupal\media\Entity\Media $entity */
    if ($valid) {
      $this->fromMedia($build, $entity);
    }

    /** @var Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem $entity */
    if (!BlazyImage::isValidItem($build)) {
      if ($item = BlazyImage::fromAny($entity, $build['settings'])) {
        // @todo revert if issues $build = NestedArray::mergeDeep($build, $item);
        $build['item'] = $item;
      }
    }

    // Attempts to get image data directly from oEmbed resource.
    // This used to be for File entity (non-media), re-purposed.
    // Extracts image item from non-media, such as Paragraphs, ER, Node, etc.
    // @todo remove when the above ::fromAny() is done right.
    // if (!BlazyImage::isValidItem($build)
    // && $stage = ($settings['image'] ?? FALSE)) {
    // BlazyImage::fromField($build, $entity, $stage);
    // }
    // Attempts to get image data directly from oEmbed resource.
    // Called by BlazyFilter or deprecated VEF, run after data populated.
    if (!$valid && (!$entity || !$blazies->get('media.embed_url'))) {
      $this->toEmbed($settings);
    }

    // Marks a hires if valid and so configured.
    if (BlazyImage::isValidItem($build)) {
      $blazies->set('is.hires', !empty($settings['image']));
    }
    else {
      // Failsafe, BlazyFilter/ VEF without file upload [data-entity-uuid].
      try {
        $build['item'] = $this->getExternalImageItem($settings);
      }
      catch (\Exception $ignore) {
        // Silently failed likely local works without internet.
      }
    }
  }

  /**
   * Modifies data to provide Media item thumbnail, embed URL, or rich content.
   *
   * @param array $build
   *   The modified array containing: settings, and candidate video thumbnail.
   * @param \Drupal\media\MediaInterface $media
   *   The core Media entity.
   */
  private function fromMedia(array &$build, MediaInterface &$media): void {
    // Prepare Media needed settings, and extract Media thumbnail.
    BlazyMedia::prepare($build, $media);
    $settings = $build['settings'];
    $blazies = $settings['blazies'];

    // @todo support local video/ audio file, and other media sources.
    // @todo check for Resource::TYPE_PHOTO, Resource::TYPE_RICH, etc.
    switch ($blazies->get('media.source')) {
      case 'oembed':
      case 'oembed:video':
      case 'video_embed_field':
        // Input url != embed url. For Youtube, /watch != /embed.
        $input = $media->getSource()->getSourceFieldValue($media);
        if ($input) {
          $blazies->set('media.input_url', $input);

          $this->toEmbed($settings);
        }
        break;

      case 'image':
        // @todo remove settings.
        $settings['type'] = 'image';
        $blazies->set('media.type', 'image');
        break;

      // No special handling for anything else for now, pass through.
      default:
        break;
    }

    // Do not proceed if it has type, already managed by theme_blazy().
    // Supports other Media entities: Facebook, Instagram, local video, etc.
    if (!$blazies->get('media.type')) {
      if ($result = BlazyMedia::build($media, $settings)) {
        $build['content'][] = $result;
      }
    }

    // Collect what's needed for clarity.
    $build['settings'] = $settings;
  }

  /**
   * Converts input URL into embed URL, run after ::prepare() populated.
   *
   * @param array $settings
   *   The settings array being modified.
   */
  private function toEmbed(array &$settings): void {
    $blazies = $settings['blazies'];
    $input = $settings['input_url'] ?? NULL;
    $input = $input ?: $blazies->get('media.input_url');

    if (empty($input)) {
      return;
    }

    $blazies->set('media.input_url', $input);
    $this->checkInputUrl($settings);

    // @todo revisit if any issue with other resource types.
    $url = Url::fromRoute('media.oembed_iframe', [], [
      'query' => [
        'url' => $input,
        'max_width' => 0,
        'max_height' => 0,
        'hash' => $this->iframeUrlHelper->getHash($input, 0, 0),
        'blazy' => 1,
        'autoplay' => empty($settings['media_switch']) ? 0 : 1,
      ],
    ]);

    if ($iframe_domain = $blazies->get('iframe_domain')) {
      $url->setOption('base_url', $iframe_domain);
    }

    // The top level iframe url relative to the site, or iframe_domain.
    // @todo remove settings after sub-modules: zooming.
    $settings['embed_url'] = $embed_url = $url->toString();

    $blazies->set('media.embed_url', $embed_url);

    if ($source = $blazies->get('media.source')) {
      $videos = in_array($source, ['oembed:video', 'video_embed_field']);
      $settings['type'] = $type = $videos ? 'video' : $source;
      $blazies->set('media.type', $type);
    }
  }

  /**
   * Gets the faked image item out of file entity, or ER, if applicable.
   *
   * This method is called by slick_browser.
   *
   * @param object $file
   *   The expected file entity, or ER, to get image item from.
   *
   * @return array
   *   The array of image item and settings if a file image, else empty.
   *
   * @todo remove after sub-modules remove this for just ::build().
   * @todo deprecated in blazy:8.x-2.9 and is removed from blazy:3.0. Use
   *   BlazyImage::fromAny() instead.
   */
  public function getImageItem($file) {
    $item = BlazyImage::fromAny($file);
    return $item ? ['item' => $item] : [];
  }

  /**
   * Gets the Media item thumbnail.
   *
   * @todo deprecated in blazy:8.x-2.9 and is removed from blazy:3.0. Use
   *   self::build() instead.
   */
  public function getMediaItem(array &$build, $media = NULL) {
    // To preserve old behaviors till sub-modules updated to ::build() at 2.9.
    // The arguments are made similar to ::build() with the new arguments.
    $this->fromMediaOrAny($build, $media);
  }

}
