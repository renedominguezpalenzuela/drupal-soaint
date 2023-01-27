<?php

namespace Drupal\Tests\php\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\RoleInterface;

/**
 * Test if PHP filter works in general.
 *
 * @group PHP
 */
abstract class PhpTestBase extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['node', 'php'];

  protected $phpCodeFormat;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Create Basic page node type.
    $this->drupalCreateContentType(['type' => 'page', 'name' => t('Basic page')]);

    // Create and login admin user.
    $admin_user = $this->drupalCreateUser(['administer filters']);
    $this->drupalLogin($admin_user);

    // Verify that the PHP code text format was inserted.
    $php_format_id = 'php_code';
    $this->phpCodeFormat = \Drupal::entityTypeManager()->getStorage('filter_format')->load($php_format_id);

    $this->assertEqual($this->phpCodeFormat->label(), 'PHP code', 'PHP code text format was created.');

    // Verify that the format has the PHP code filter enabled.
    $filters = $this->phpCodeFormat->filters();
    $this->assertTrue($filters->get('php_code')->status, 'PHP code filter is enabled.');

    // Verify that the format exists on the administration page.
    $this->drupalGet('admin/config/content/formats');
    $this->assertText('PHP code', 'PHP code text format was created.');

    // Verify that anonymous and authenticated user roles do not have access.
    $this->drupalGet('admin/config/content/formats/manage/' . $php_format_id);
    $this->assertFieldByName('roles[' . RoleInterface::ANONYMOUS_ID . ']', FALSE, 'Anonymous users do not have access to PHP code format.');
    $this->assertFieldByName('roles[' . RoleInterface::AUTHENTICATED_ID . ']', FALSE, 'Authenticated users do not have access to PHP code format.');
  }

  /**
   * Creates a test node with PHP code in the body.
   *
   * @return \Drupal\node\NodeInterface
   *   Node object.
   */
  public function createNodeWithCode() {
    return $this->drupalCreateNode(['body' => [['value' => '<?php print "SimpleTest PHP was executed!";print "Current state is " . Drupal::state()->get("php_state_test", "empty"); ?>']]]);
  }

}
