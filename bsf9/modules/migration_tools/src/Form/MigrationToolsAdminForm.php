<?php

namespace Drupal\migration_tools\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\RfcLogLevel;

/**
 * Class MigrationToolsAdminForm.
 */
class MigrationToolsAdminForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'migration_tools_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('migration_tools.settings');

    $options = ['0' => 'All'];
    $options += RfcLogLevel::getLevels();

    $form['debug_level'] = [
      '#type' => 'select',
      '#title' => $this->t('Debug Level'),
      '#weight' => '0',
      '#options' => $options,
      '#default_value' => $config->get('debug_level'),
    ];
    $form['debug'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Debug Logging'),
      '#weight' => '0',
      '#default_value' => $config->get('debug'),
    ];
    $form['drush_debug'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Drush Debug'),
      '#weight' => '0',
      '#default_value' => $config->get('drush_debug'),
    ];
    $form['drush_stop_on_error'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Drush Stop On Error'),
      '#weight' => '0',
      '#default_value' => $config->get('drush_stop_on_error'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = self::configFactory()->getEditable('migration_tools.settings');
    $config
      ->set('debug_level', $form_state->getValue('debug_level'))
      ->set('debug', $form_state->getValue('debug'))
      ->set('drush_debug', $form_state->getValue('drush_debug'))
      ->set('drush_stop_on_error', $form_state->getValue('drush_stop_on_error'))
      ->save();
  }

}
