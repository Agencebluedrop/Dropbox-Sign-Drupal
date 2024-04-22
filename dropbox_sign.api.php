<?php

/**
 * @file
 * Describes API functions for tour module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Allow modules to take actions when Dropbox Sign makes an esignature callback.
 *
 * This hook is called whenever Dropbox Sign makes a call to the site,
 * such as after a document has been signed. For details about the types of
 * events that happen and what this data structure contains, please refer to the
 * Dropbox Sign docs
 * (https://sign.dropbox.com/products/dropbox-sign-api).
 *
 * @param object $data
 *   The data sent by Dropbox Sign.
 */
function hook_process_dropbox_sign_callback($data) {
  // Get event info.
  $event_type = $data->event->event_type;

  if ($event_type == 'signature_request_signed') {
    \Drupal::logger('dropbox_sign')->info(t('Someone has signed Dropbox signature request @id.', ['@id' => $data->signature_request->signature_request_id]));
  }
}

/**
 * @} End of "addtogroup hooks".
 */
