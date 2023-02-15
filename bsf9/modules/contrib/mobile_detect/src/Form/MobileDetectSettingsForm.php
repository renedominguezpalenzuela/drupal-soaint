<?php

namespace Drupal\mobile_detect\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Mobile Detect settings for this site.
 */
class MobileDetectSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mobile_detect_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['mobile_detect.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['mobile_detect_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Experimental')
    ];
    $form['mobile_detect_settings']['mobile_detect_is_mobile'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add "mobile_detect_is_mobile" page cache context on every page (experimental).'),
      '#default_value' => $this->config('mobile_detect.settings')->get('mobile_detect_is_mobile'),
      '#description' => $this->t('If you need <i>mobile_detect_is_mobile</i> cache context on every page, check this option.')
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->config('mobile_detect.settings')
      ->set('mobile_detect_is_mobile', $form_state->getValue('mobile_detect_is_mobile'))
      ->save();
  }

}
