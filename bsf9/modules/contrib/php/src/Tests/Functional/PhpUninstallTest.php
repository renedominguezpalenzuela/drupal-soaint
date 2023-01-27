<?php

namespace Drupal\Tests\php\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test uninstall functionality of PHP module.
 *
 * @group PHP
 */
class PhpUninstallTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['php'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $permissions = [
      'access administration pages',
      'administer modules',
    ];

    // User to set up php.
    $this->admin_user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($this->admin_user);
  }

  /**
   * Tests if the module cleans up the disk on uninstall.
   */
  public function testPhpUninstall() {
    // If this request is missing the uninstall form shows "The form has become
    // outdated. Copy any unsaved work in the form below and then reload this
    // page." message for unknown reasons.
    $this->drupalGet('admin/modules');

    // Uninstall the module.
    $edit = [];
    $edit['uninstall[php]'] = TRUE;
    $this->drupalPostForm('admin/modules/uninstall', $edit, t('Uninstall'));
    $this->assertText(t('Would you like to continue with uninstalling the above?'));
    $this->drupalPostForm(NULL, [], t('Uninstall'));
    $this->assertText(t('The selected modules have been uninstalled.'));
  }

}
