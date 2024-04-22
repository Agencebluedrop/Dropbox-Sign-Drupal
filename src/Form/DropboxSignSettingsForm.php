<?php

namespace Drupal\dropbox_sign\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\encryption\EncryptionService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Dropbox Sign settings for this site.
 */
class DropboxSignSettingsForm extends ConfigFormBase {

  use StringTranslationTrait;

  /**
   * The encryption service.
   *
   * @var \Drupal\encryption\EncryptionService
   */
  protected $encryption;

  /**
   * Constructs a \Drupal\dropbox_sign\Form\DropboxSignSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\encryption\EncryptionService $encryption
   *   The encryption service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EncryptionService $encryption) {
    parent::__construct($config_factory);
    $this->encryption = $encryption;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('encryption')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dropbox_sign_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['dropbox_sign.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('dropbox_sign.settings');

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Dropbox Sign API Key'),
      '#default_value' => $config->get('api_key') ? $this->encryption->decrypt($config->get('api_key'), TRUE) : '',
      '#required' => TRUE,
    ];
    $form['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Dropbox Sign Client ID'),
      '#default_value' => $config->get('client_id') ? $this->encryption->decrypt($config->get('client_id'), TRUE) : '',
    ];
    $form['cc_emails'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CC email addresses'),
      '#description' => $this->t('Email addresses that will be copied on all requests, but will not have a signer role. Separate multiple email addresses with a comma.'),
      '#default_value' => $config->get('cc_emails'),
    ];
    $form['test_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Test mode'),
      '#default_value' => $config->get('test_mode'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Validate the Dropbox Sign API key.
    $api_key = $form_state->getValue('api_key');
    $error_message = dropbox_sign_validate_dropboxsign_api_key($api_key);
    if ($error_message) {
      $form_state->setErrorByName('api_key', $this->t('@error_message', ['@error_message' => $error_message]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $encrypted_api_key = $this->encryption->encrypt($form_state->getValue('api_key'), TRUE);
    $encrypted_client_id = $this->encryption->encrypt($form_state->getValue('client_id'), TRUE);

    if (is_null($encrypted_api_key) || is_null($encrypted_client_id)) {
      $this->messenger()->addError($this->t('Failed to encrypt the API Key and/or Client ID. Please ensure that the Encryption module is enabled and that an encryption key has been set.'));
    }

    $this->config('dropbox_sign.settings')
      ->set('api_key', $encrypted_api_key)
      ->set('client_id', $encrypted_client_id)
      ->set('cc_emails', $form_state->getValue('cc_emails'))
      ->set('test_mode', $form_state->getValue('test_mode'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
