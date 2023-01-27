<?php

namespace Drupal\Tests\blazy\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\blazy\Media\BlazyMedia;
use Drupal\blazy\BlazyDefault;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Tests the Blazy image formatter.
 *
 * @coversDefaultClass \Drupal\blazy\Plugin\Field\FieldFormatter\BlazyImageFormatter
 *
 * @group blazy
 */
class BlazyFormatterTest extends BlazyKernelTestBase {

  /**
   * The formatter instance.
   *
   * @var \Drupal\blazy\Plugin\Field\FieldFormatter\BlazyImageFormatter
   */
  protected $formatterInstance;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $data['fields'] = [
      'field_video' => 'image',
      'field_image' => 'image',
      'field_id'    => 'text',
    ];

    // Create contents.
    $bundle = $this->bundle;
    $this->setUpContentTypeTest($bundle, $data);

    $data['settings'] = $this->getFormatterSettings();
    $this->display = $this->setUpFormatterDisplay($bundle, $data);

    $this->setUpContentWithItems($bundle);
    $this->setUpRealImage();

    $this->formatterInstance = $this->getFormatterInstance();
  }

  /**
   * Tests the Blazy formatter buid methods.
   */
  public function testBlazyFormatterCache() {
    // Tests type definition.
    $this->typeDefinition = $this->blazyAdminFormatter
      ->getTypedConfig()
      ->getDefinition('blazy.settings');

    $this->assertEquals('Blazy settings', $this->typeDefinition['label']);

    // Tests cache.
    $entity = $this->entity;
    $build = $this->display->build($entity);

    $this->assertInstanceOf('\Drupal\Core\Field\FieldItemListInterface', $this->testItems, 'Field implements interface.');
    $this->assertInstanceOf('\Drupal\blazy\BlazyManagerInterface', $this->formatterInstance->blazyManager(), 'BlazyManager implements interface.');

    // Tests cache tags matching entity ::getCacheTags().
    $item = $entity->get($this->testFieldName);
    $field = $build[$this->testFieldName];

    // Verify it is a theme_field().
    $this->assertArrayHasKey('#blazy', $field);
    $this->assertArrayHasKey('#build', $field[0]);

    // Verify it is not a theme_item_list() grid.
    $this->assertArrayNotHasKey('#build', $field);

    $settings0 = $field[0]['#build']['settings'];
    $settings1 = $field[1]['#build']['settings'];

    $blazies0 = $settings0['blazies'];
    $blazies1 = $settings1['blazies'];
    $file0 = $item[0]->entity;
    $file1 = $item[1]->entity;

    $tag0 = [$blazies0->get('cache.file.tags')[0]];
    $tag1 = [$blazies1->get('cache.file.tags')[0]];

    $this->assertEquals($file0->getCacheTags(), $tag0, 'First image cache tags is as expected');
    $this->assertEquals($file1->getCacheTags(), $tag1, 'Second image cache tags is as expected');

    $render = $this->blazyManager->getRenderer()->renderRoot($build);
    $this->assertNotEmpty($render);
    $this->assertStringContainsString('data-blazy', $render);
  }

  /**
   * Tests the Blazy formatter settings form.
   */
  public function testBlazySettingsForm() {
    // Tests ::settingsForm.
    $form = [];

    // Check for setttings form.
    $form_state = new FormState();
    $elements = $this->formatterInstance->settingsForm($form, $form_state);
    $this->assertArrayHasKey('opening', $elements);
    $this->assertArrayHasKey('closing', $elements);
  }

  /**
   * Tests the Blazy formatter view display.
   */
  public function testFormatterViewDisplay() {
    $formatter_settings = $this->formatterInstance->buildSettings();
    $this->assertArrayHasKey('blazies', $formatter_settings);

    $blazies = $formatter_settings['blazies'];
    $this->assertArrayHasKey('field', $blazies->storage());

    $this->assertEquals($this->testPluginId, $blazies->get('field.plugin_id'));

    // 1. Tests formatter settings.
    $build = $this->display->build($this->entity);

    $result = $this->entity
      ->get($this->testFieldName)
      ->view(['type' => 'blazy']);

    $this->assertEquals('blazy', $result[0]['#theme']);

    $component = $this->display->getComponent($this->testFieldName);

    $this->assertEquals($this->testPluginId, $component['type']);
    $this->assertEquals($this->testPluginId, $build[$this->testFieldName]['#formatter']);

    $format['settings'] = array_merge($this->getFormatterSettings(), $formatter_settings);

    $settings = &$format['settings'];
    $blazies = $settings['blazies'];
    // @todo remove.
    $blazies->set('is.blazy', TRUE)
      ->set('lazy.id', 'blazy');

    // 2. Test theme_field(), no grid.
    $settings['bundle']          = $this->bundle;
    $settings['grid']            = 0;
    $settings['background']      = TRUE;
    $settings['thumbnail_style'] = 'thumbnail';
    $settings['ratio']           = 'fluid';
    $settings['image_style']     = 'blazy_crop';

    try {
      $settings['vanilla'] = TRUE;
      $this->blazyFormatter->buildSettings($format, $this->testItems);
    }
    catch (\PHPUnit_Framework_Exception $e) {
    }

    $this->assertEquals($this->testFieldName, $blazies->get('field.name'));

    $settings['vanilla'] = FALSE;
    // $this->blazyFormatter->buildSettings($format, $this->testItems);
    $this->blazyFormatter->preBuildElements($format, $this->testItems);

    // Blazy uses theme_field() output.
    $this->assertEquals($this->testFieldName, $blazies->get('field.name'));
    $this->assertArrayHasKey('#blazy', $build[$this->testFieldName]);

    $options = $this->blazyAdminFormatter->getOptionsetOptions('image_style');
    $this->assertArrayHasKey('large', $options);

    // 3. Tests grid.
    $new_settings = $this->getFormatterSettings();

    $new_settings['grid']         = '4';
    $new_settings['grid_medium']  = '3';
    $new_settings['grid_small']   = '2';
    $new_settings['media_switch'] = 'blazy_test';
    $new_settings['style']        = 'column';
    $new_settings['image_style']  = 'blazy_crop';

    $this->display->setComponent($this->testFieldName, [
      'type'     => $this->testPluginId,
      'settings' => $new_settings,
      'label'    => 'hidden',
    ]);

    $build = $this->display->build($this->entity);

    // Verify theme_field() is taken over by Grid::build().
    $this->assertArrayNotHasKey('#blazy', $build[$this->testFieldName]);
  }

  /**
   * Tests the Blazy formatter faked Media integration.
   *
   * @param mixed|string|bool $input_url
   *   Input URL, else empty.
   * @param bool $expected
   *   The expected output.
   *
   * @dataProvider providerTestBlazyMedia
   */
  public function testBlazyMedia($input_url, $expected) {
    // Attempts to fix undefined DRUPAL_TEST_IN_CHILD_SITE for PHP 8 at 9.1.x.
    // The middleware test.http_client.middleware calls drupal_generate_test_ua
    // which checks the DRUPAL_TEST_IN_CHILD_SITE constant, that is not defined
    // in Kernel tests.
    try {
      if (!defined('DRUPAL_TEST_IN_CHILD_SITE')) {
        define('DRUPAL_TEST_IN_CHILD_SITE', FALSE);
      }

      $entity = $this->entity;

      $settings = [
        'input_url'       => $input_url,
        // 'source_field'    => $this->testFieldName,
        // 'media_source'    => 'remote_video',
        // 'view_mode'       => 'default',
        'bundle'          => $this->bundle,
        'thumbnail_style' => 'thumbnail',
        'uri'             => $this->uri,
      ] + BlazyDefault::htmlSettings();

      $blazies = &$settings['blazies'];
      $info = [
        'input_url'    => $input_url,
        'source_field' => $this->testFieldName,
        'source'       => 'remote_video',
        'view_mode'    => 'default',
      ];

      $blazies->set('media', $info);

      $build = $this->display->build($entity);

      $render = BlazyMedia::build($entity, $settings);

      if ($expected && $render) {
        $this->assertNotEmpty($render);

        $field[0] = $render;
        $field['#settings'] = $settings;
        $wrap = BlazyMedia::unfield($field, $settings);
        $this->assertNotEmpty($wrap);

        $render = $this->blazyManager->getRenderer()->renderRoot($build[$this->testFieldName]);
        $this->assertStringContainsString('data-blazy', $render);
      }
      else {
        $this->assertEmpty($render);
      }
    }
    catch (GuzzleException $e) {
      // Ignore any HTTP errors.
    }
  }

  /**
   * Provide test cases for ::testBlazyMedia().
   *
   * @return array
   *   An array of tested data.
   */
  public function providerTestBlazyMedia() {
    return [
      ['', TRUE],
      ['http://xyz123.com/x/123', FALSE],
      ['user', TRUE],
    ];
  }

}
