<?php

namespace Drupal\Tests\blazy\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\blazy\Media\BlazyMedia;
use Drupal\blazy\BlazyDefault;
use Drupal\Tests\blazy\Traits\BlazyUnitTestTrait;

/**
 * @coversDefaultClass \Drupal\blazy\BlazyMedia
 *
 * @group blazy
 */
class BlazyMediaUnitTest extends UnitTestCase {

  use BlazyUnitTestTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setUpVariables();
    $this->setUpUnitImages();
  }

  /**
   * Tests \Drupal\blazy\BlazyMedia::build().
   *
   * @covers ::build
   * @covers ::wrap
   * @dataProvider providerTestBlazyMediaBuild
   */
  public function testBlazyMediaBuild($markup) {
    $source_field = $this->randomMachineName();
    $view_mode = 'default';
    $settings = [
      // 'source_field' => $source_field,
      'image_style'  => 'blazy_crop',
      'ratio'        => 'fluid',
      // 'view_mode'    => 'default',
      // 'media_source' => 'remote_video',
      'media_switch' => 'media',
      // @todo 'bundle' => 'entity_test',
    ] + BlazyDefault::htmlSettings();

    $blazies = &$settings['blazies'];
    $info = [
      // 'input_url'    => $input_url,
      'source_field' => $source_field,
      'source'       => 'remote_video',
      'view_mode'    => $view_mode,
    ];

    $blazies->set('media', $info);

    $markup['#settings'] = $settings;
    $markup['#attached'] = [];
    $markup['#cache']    = [];

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->createMock('Drupal\Core\Entity\ContentEntityInterface');
    $field_definition = $this->createMock('Drupal\Core\Field\FieldDefinitionInterface');

    $items = $this->createMock('Drupal\Core\Field\FieldItemListInterface');
    $items->expects($this->any())
      ->method('getFieldDefinition')
      ->willReturn($field_definition);
    $items->expects($this->any())
      ->method('view')
      ->with($view_mode)
      ->willReturn($markup);
    $items->expects($this->any())
      ->method('getEntity')
      ->willReturn($entity);

    $entity->expects($this->once())
      ->method('get')
      ->with($source_field)
      ->will($this->returnValue($items));

    $render = BlazyMedia::build($entity, $settings);
    $this->assertArrayHasKey('#settings', $render);
  }

  /**
   * Provider for ::testBlazyMediaBuild.
   */
  public function providerTestBlazyMediaBuild() {
    $iframe = [
      '#type' => 'html_tag',
      '#tag' => 'iframe',
      '#attributes' => [
        'allowfullscreen' => 'true',
        'frameborder' => 0,
        'scrolling' => 'no',
        'src' => '//www.youtube.com/watch?v=E03HFA923kw',
        'width' => 640,
        'height' => 360,
      ],
    ];

    $markup['#markup'] = '<iframe src="//www.youtube.com/watch?v=E03HFA923kw" class="b-lazy"></iframe>';

    return [
      'With children, has iframe tag' => [
        [$iframe],
      ],
      'Without children, has iframe tag' => [
        $iframe,
      ],
      'With children, has no iframe tag' => [
        [$markup],
      ],
    ];
  }

}
