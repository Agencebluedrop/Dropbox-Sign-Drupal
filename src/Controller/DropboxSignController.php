<?php

namespace Drupal\dropbox_sign\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\encryption\EncryptionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Returns responses for Dropbox Sign routes.
 */
class DropboxSignController extends ControllerBase {

  /**
   * The dropbox_sign.settings config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The encryption service.
   *
   * @var \Drupal\encryption\EncryptionService
   */
  protected $encryption;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a DropboxSignController object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\encryption\EncryptionService $encryption
   *   The encryption service.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EncryptionService $encryption, LoggerInterface $logger, ModuleHandlerInterface $module_handler, TimeInterface $time) {
    $this->config = $config_factory->get('dropbox_sign.settings');
    $this->encryption = $encryption;
    $this->logger = $logger;
    $this->moduleHandler = $module_handler;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('encryption'),
      $container->get('logger.channel.dropbox_sign'),
      $container->get('module_handler'),
      $container->get('datetime.time')
    );
  }

  /**
   * Handles a signature callback request made by Dropbox Sign.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Dropbox Sign's callback expects a simple text response with the message
   *   "Dropbox Sign API Event Received" rather than a fully rendered webpage.
   */
  public function signatureCallback(RequestStack $requestStack) {
    $currentRequest = $requestStack->getCurrentRequest();

    if (!$currentRequest->request->has('json')) {
      $this->logger->error('Dropbox Sign callback failed because no POST data was supplied.');
      throw new AccessDeniedHttpException();
    }

    $data = json_decode($currentRequest->request->get('json'));

    if (!$data) {
      $this->logger->error('Dropbox Sign callback failed because the supplied POST data could not be parsed.');
      throw new AccessDeniedHttpException();
    }

    // Extract needed fields from the data.
    $event_hash = $data->event->event_hash;
    $event_time = $data->event->event_time;
    $event_type = $data->event->event_type;

    // Dropbox Sign makes test requests when setting the callback URL
    // which should return a successful response when called.
    if ($event_type == 'callback_test') {
      // This return is per the Dropbox Sign API spec and shouldn't be changed.
      return new Response('Dropbox Sign API Event Received');
    }

    $signature_request_id = $data->signature_request->signature_request_id;

    // Prevent old requests from being replayed by verifying that the event
    // timestamp is within the last 24 hours. Note that Dropbox Sign retries
    // failed event notifications for up to ~20 hours after the first attempt
    // (see
    // https://sign.dropbox.com/products/dropbox-sign-api#FailuresAndRetries).
    // 24 hours should be enough time for any legitimate event to be received.
    if ($event_time < $this->time->getRequestTime() - 86400) {
      $this->logger->error('Dropbox Sign callback for signature request @id failed timestamp verification (event timestamp was @timestamp which is more than 24 hours old).',
      ['@id' => $signature_request_id, '@timestamp' => $event_time]);
      throw new AccessDeniedHttpException();
    }

    // Verify the event hash. If there is no API key, this should always fail
    // validation (since without an API key, the hash can easily be faked).
    $api_key = $this->encryption->decrypt($this->config->get('api_key'), TRUE);
    if (!$api_key || hash_hmac('sha256', $event_time . $event_type, $api_key) !== $event_hash) {
      $this->logger->error('Dropbox Sign callback for signature request @id failed hash verification.', ['@id' => $signature_request_id]);
      throw new AccessDeniedHttpException();
    }

    // The callback seems to have been successful.
    $this->logger->info('Dropbox Sign callback received for signature request @id.', ['@id' => $signature_request_id]);

    // Send information to all modules requesting this callback.
    $this->moduleHandler->invokeAll('process_dropbox_sign_callback', [$data]);

    // This return is per the Dropbox Sign API spec and shouldn't be changed.
    return new Response('Dropbox Sign API Event Received');
  }

}
