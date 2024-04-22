<?php

namespace Drupal\dropbox_sign;

require_once '../vendor/autoload.php';

use Dropbox\Sign\Api\EmbeddedApi;
use Dropbox\Sign\Api\SignatureRequestApi;
use Dropbox\Sign\ApiException;
use Dropbox\Sign\Configuration;
use Dropbox\Sign\Model\SignatureRequestCreateEmbeddedRequest;
use Dropbox\Sign\Model\SignatureRequestSendRequest;
use Dropbox\Sign\Model\SubSignatureRequestSigner;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\encryption\EncryptionService;
use Psr\Log\LoggerInterface;

/**
 * Establishes a connection to Dropbox Sign API.
 */
class DropboxSign {

  use StringTranslationTrait;

  /**
   * The dropbox_sign.settings config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The Dropbox SignatureRequestApi.
   *
   * @var \Dropbox\Sign\Api\SignatureRequestApi
   */
  protected $signatureApi;

  /**
   * The Dropbox EmbeddedApi.
   *
   * @var \Dropbox\Sign\Api\EmbeddedApi
   */
  protected $embeddedApi;

  /**
   * The encryption service.
   *
   * @var \Drupal\encryption\EncryptionService
   */
  protected $encryption;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The Dropbox sign configuration.
   *
   * @var \Dropbox\Sign\Configuration
   */
  protected $signConfig;

  /**
   * Establishes the connection to Dropbox Sign API.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\encryption\EncryptionService $encryption
   *   The encryption service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   *
   * @throws \Exception
   */
  public function __construct(ConfigFactoryInterface $config_factory, EncryptionService $encryption, FileSystemInterface $file_system, LoggerInterface $logger, TranslationInterface $string_translation) {
    $this->config = $config_factory->get('dropbox_sign.settings');
    $this->encryption = $encryption;
    $this->fileSystem = $file_system;
    $this->logger = $logger;
    $this->stringTranslation = $string_translation;

    $api_key = $this->encryption->decrypt($this->config->get('api_key'), TRUE);

    if (!$api_key) {
      throw new \Exception('Could not connect to Dropbox Sign because no API key has been set.');
    }

    $this->signConfig = Configuration::getDefaultConfiguration();
    $this->signConfig->setUsername($api_key);

    $this->signatureApi = new SignatureRequestApi($this->signConfig);
    $this->embeddedApi = new EmbeddedApi($this->signConfig);

  }

  /**
   * Gets the SignatureRequestApi.
   *
   * @return \Dropbox\Sign\Api\SignatureRequestApi
   *   The Signature Request API.
   */
  public function getSignatureRequestApi() {
    return $this->signatureApi;
  }

  /**
   * Helper function for creating a new Dropbox Sign eSignature request.
   *
   * @param string $title
   *   Document title.
   * @param string $subject
   *   Email subject.
   * @param array $signers
   *   Array of signers with a key of email address and a value of name.
   * @param string $file
   *   A full path to a local system file.
   * @param string $mode
   *   (optional) The type of signature request, either "embedded" or "email".
   * @param string $redirectUrl
   *   (optional) The url to redirect after signature.
   * @param string $msg
   *   (optional) The message to be sent in the signature request email.
   *
   * @throws \Exception
   *
   * @return array
   *   Contains the signature_request_id and an array of signatures.
   */
  public function createSignatureRequest(?string $title, ?string $subject, array $signers, $file, $mode = 'email', ?string $redirectUrl = NULL, ?string $msg = NULL): array {
    $this->logger->debug('Generating %mode signature request %title using file %file.', [
      '%title' => $title,
      '%file' => $file,
      '%mode' => $mode,
    ]);

    // Attempt to create new signature request.
    $signatureRequest = $this->signatureApi;

    // Add all signers to list.
    $signerCount = 0;
    $signersList = [];
    foreach ($signers as $signer_email => $signer_name) {
      $signer = new SubSignatureRequestSigner();
      $signer->setEmailAddress($signer_email)
        ->setName($signer_name)
        ->setOrder($signerCount);
      $signersList[] = $signer;
      ++$signerCount;
    }

    if ($mode == 'embedded') {
      $client_id = $this->encryption->decrypt($this->config->get('client_id'), TRUE);
      if (!$client_id) {
        throw new \Exception('A Dropbox Sign Client ID must be set in order to create embedded signature requests.');
      }

      // Create an Embedded Signature Request object with
      // the information for the request.
      $data = new SignatureRequestCreateEmbeddedRequest();
      $data->setClientId($client_id)
        ->setTitle($title)
        ->setSubject($subject)
        ->setMessage($msg)
        ->setSigners($signersList)
        ->setFiles([$file])
        ->setUseTextTags(TRUE)
        ->setHideTextTags(TRUE);
    }
    else {
      // Create a Signature Request object with the information for the request.
      $data = new SignatureRequestSendRequest();
      $data->setTitle($title)
        ->setSubject($subject)
        ->setMessage($msg)
        ->setSigners($signersList)
        ->setFiles([$file])
        ->setSigningRedirectUrl($redirectUrl)
        ->setUseTextTags(TRUE)
        ->setHideTextTags(TRUE);
    }

    // If selected, place in test mode.
    if ($this->config->get('test_mode')) {
      $data->setTestMode(TRUE);
    }

    // Add cc emails (non signers).
    $cc_emails = $this->config->get('cc_emails');
    if ($cc_emails) {
      $cc_emails = explode(',', (string) $cc_emails);
      $data->setCcEmailAddresses($cc_emails);
    }

    // Initiate request based on mode.
    try {
      switch ($mode) {
        // Send the request to Dropbox Sign.
        case 'email':
          /** @var SignatureRequestGetResponse $response  */
          $response = $signatureRequest->signatureRequestSend($data);
          break;

        case 'embedded':
          /** @var SignatureRequestGetResponse $response  */
          $response = $signatureRequest->signatureRequestCreateEmbedded($data);
          break;

        default:
          throw new \Exception('The specified signature request mode was not recognized.');
      }

      return [
        'signature_request_id' => $response->getSignatureRequest()['signature_request_id'],
        'signatures' => $response->getSignatureRequest()->getSignatures(),
      ];
    }
    catch (ApiException $apiException) {
      $error = $apiException->getResponseObject();
      $error_message = $this->t("Exception when calling Dropbox Sign API:") . print_r($error->getError(), TRUE);

      // Log the error message.
      $this->logger->error($error_message);

      return [];
    }
  }

  /**
   * Helper function for creating a new Embedded eSignature Url.
   *
   * @param string $signatureId
   *   Signature Id.
   *
   * @throws \Exception
   *
   * @return array
   *   Contains the signature_request_id and an array of signatures.
   */
  public function getSignUrl(?string $signatureId): array {

    try {
      /** @var  \Dropbox\Sign\Model\EmbeddedSignUrlResponse $result */
      $result = $this->embeddedApi->embeddedSignUrl($signatureId);

      return [
        'sign_url' => $result->getEmbedded()->getSignUrl(),
      ];
    }
    catch (ApiException $e) {
      $error = $e->getResponseObject();
      $error_message = $this->t("Exception when calling Dropbox Sign API:") . print_r($error->getError(), TRUE);

      // Log the error message.
      $this->logger->error($error_message);

      return [];
    }
  }

}
