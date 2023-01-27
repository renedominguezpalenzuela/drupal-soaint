<?php

namespace Drupal\Tests\blazy\FunctionalJavascript;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderContext;
use Drupal\FunctionalJavascriptTests\DrupalSelenium2Driver;
use Drupal\filter\Entity\FilterFormat;
use Drupal\filter\FilterPluginCollection;
use Drupal\filter\FilterProcessResult;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\blazy\Blazy;
use Drupal\blazy\BlazyDefault;
use Drupal\Tests\blazy\Traits\BlazyUnitTestTrait;
use Drupal\Tests\blazy\Traits\BlazyCreationTestTrait;

/**
 * Tests the Blazy Filter JavaScript using Selenium, or Chromedriver.
 *
 * @group blazy
 */
class BlazyFilterJavaScriptTest extends WebDriverTestBase {

  use BlazyUnitTestTrait;
  use BlazyCreationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected $minkDefaultDriverClass = DrupalSelenium2Driver::class;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'filter',
    'image',
    'media',
    'node',
    'text',
    'blazy',
    'blazy_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setUpVariables();

    $this->root                   = Blazy::root($this->container);
    $this->fileSystem             = $this->container->get('file_system');
    $this->entityFieldManager     = $this->container->get('entity_field.manager');
    $this->formatterPluginManager = $this->container->get('plugin.manager.field.formatter');
    $this->blazyAdmin             = $this->container->get('blazy.admin');
    $this->blazyOembed            = $this->container->get('blazy.oembed');
    $this->blazyManager           = $this->container->get('blazy.manager');
    $this->testPluginId           = 'blazy_filter';
    $this->maxParagraphs          = 280;

    // Create a text format.
    $full_html = FilterFormat::create([
      'format' => 'full_html',
      'name' => 'Full HTML',
      'weight' => 0,
    ]);
    $full_html->save();

    // Enable the Blazy filter.
    $this->filterFormatFull = FilterFormat::load('full_html');
    $this->filterFormatFull->setFilterConfig('blazy_filter', [
      'status' => TRUE,
    ]);
    $this->filterFormatFull->save();

    $this->setUpRealImage();
  }

  /**
   * Test the Blazy filter has media-wrapper--blazy for IMG and IFRAME elements.
   */
  public function testFilterDisplay() {
    $image_path = $this->getImagePath(TRUE);
    $settings = BlazyDefault::htmlSettings();
    $settings['extra_text'] = $text = $this->dummyText();

    $this->setUpContentTypeTest($this->bundle);
    $this->setUpContentWithItems($this->bundle, $settings);

    $session = $this->getSession();

    $this->drupalGet('node/' . $this->entity->id());

    // Ensures Blazy is not loaded on page load.
    // @todo with Native lazyload, b-loaded is enforced on page load. And
    // since the testing browser Chrome support it, it is irrelevant.
    // @todo $this->assertSession()->elementNotExists('css', '.b-loaded');
    // Capture the initial page load moment.
    $this->createScreenshot($image_path . '/1_blazy_filter_initial.png');
    $this->assertSession()->elementExists('css', '.b-lazy');

    // Trigger Blazy to load images by scrolling down window.
    $session->executeScript('window.scrollTo(0, document.body.scrollHeight);');

    // Capture the loading moment after scrolling down the window.
    $this->createScreenshot($image_path . '/2_blazy_filter_loading.png');

    // Verifies that our filter works identified by media-wrapper--blazy class.
    $this->assertSession()->elementExists('css', '.media-wrapper--blazy');
    $this->assertSession()->elementContains('css', '.media-wrapper--blazy', 'b-lazy');

    // Also verifies that [data-unblazy] should not be touched, nor lazyloaded.
    $this->assertSession()->elementNotContains('css', '.media-wrapper--blazy', 'data-unblazy');

    // Verifies that one of the images is there once loaded.
    $loaded = $this->assertSession()->waitForElement('css', '.b-loaded');
    $this->assertNotEmpty($loaded);

    // Capture the loaded moment.
    // The screenshots are at sites/default/files/simpletest/blazy.
    $this->createScreenshot($image_path . '/3_blazy_filter_loaded.png');

    // Verifies the library is loaded.
    ['result' => $result, 'html' => $html] = $this->applyFilter($text);
    $this->assertNotSame($html, $text);
    $attachments = $result->getAttachments();
    $this->assertContains('blazy/filter', $attachments['library']);
    $this->assertArrayHasKey('blazy', $attachments['drupalSettings']);

    // Check external image item from resource relevant to BlazyFilter.
    // Too risky when the video is removed causing false positive.
    // $settings['input_url'] = 'https://www.youtube.com/watch?v=uny9kbh4iOEd';
    // $item = $this->blazyOembed->build($settings);
    // $this->assertNotEmpty($item);
  }

  /**
   * Applies the `@Filter=blazy_fiter` filter to text, pipes to raw content.
   *
   * @param string $text
   *   The text string to be filtered.
   * @param string $identifier
   *   The any text which identifies this filter.
   * @param string $langcode
   *   The language code of the text to be filtered.
   *
   * @return \Drupal\filter\FilterProcessResult
   *   The filtered text, wrapped in a FilterProcessResult object, and possibly
   *   with associated assets, cacheability metadata and placeholders.
   */
  protected function applyFilter($text, $identifier = 'media-wrapper--blazy', $langcode = 'en') {
    $this->assertStringNotContainsString($identifier, $text);
    $result = $this->processText($text, $langcode);
    $html = $result->getProcessedText();
    $this->assertStringContainsString($identifier, $html);

    return ['result' => $result, 'html' => $html];
  }

  /**
   * Processes text through the provided filters, taken from media embed.
   *
   * @param string $text
   *   The text string to be filtered.
   * @param string $langcode
   *   The language code of the text to be filtered.
   * @param string[] $filter_ids
   *   (optional) The filter plugin IDs to apply to the given text, in the order
   *   they are being requested to be executed.
   *
   * @return \Drupal\filter\FilterProcessResult
   *   The filtered text, wrapped in a FilterProcessResult object, and possibly
   *   with associated assets, cacheability metadata and placeholders.
   *
   * @see \Drupal\filter\Element\ProcessedText::preRenderText()
   */
  protected function processText($text, $langcode = LanguageInterface::LANGCODE_NOT_SPECIFIED, array $filter_ids = ['blazy_filter']) {
    $manager = $this->container->get('plugin.manager.filter');
    $bag = new FilterPluginCollection($manager, []);
    $filters = [];
    foreach ($filter_ids as $filter_id) {
      $filters[] = $bag->get($filter_id);
    }

    $render_context = new RenderContext();
    /** @var \Drupal\filter\FilterProcessResult $filter_result */
    $filter_result = $this->container->get('renderer')->executeInRenderContext($render_context, function () use ($text, $filters, $langcode) {
      $metadata = new BubbleableMetadata();
      foreach ($filters as $filter) {
        /** @var \Drupal\filter\FilterProcessResult $result */
        $result = $filter->process($text, $langcode);
        $metadata = $metadata->merge($result);
        $text = $result->getProcessedText();
      }
      return (new FilterProcessResult($text))->merge($metadata);
    });
    if (!$render_context->isEmpty()) {
      $filter_result = $filter_result->merge($render_context->pop());
    }
    return $filter_result;
  }

  /**
   * Returns a dummy text.
   */
  protected function dummyText(): string {
    $uuid = $this->dummyItem->uuid();
    $text = '<div style="width: 640px;">';
    $text .= '<img data-unblazy src="' . $this->url . '" width="320" height="320" />';
    $text .= '<iframe src="https://www.youtube.com/watch?v=uny9kbh4iOEd" width="640" height="360"></iframe>';
    $text .= '<img src="' . $this->url . '" width="320" height="320" />';
    $text .= '<img src="' . $this->dummyUrl . '" width="320" height="320" data-entity-type="file" data-entity-uuid="' . $uuid . '"/>';
    $text .= '<img src="https://www.drupal.org/files/project-images/slick-carousel-drupal.png" width="215" height="162" />';
    $text .= '</div>';

    return $text;
  }

}
