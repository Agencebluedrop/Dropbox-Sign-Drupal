INTRODUCTION
------------
The Dropbox Sign module is a Drupal integration for
the Dropbox Sign electronic signature API.
This module enables seamless integration with the Dropbox Sign API,
allowing users to manage and process electronic signature requests
directly from Drupal. 

This module was inspired by and based on the original [HelloSign](https://www.drupal.org/project/hellosign) module,
which integrated Drupal with the HelloSign API.
As the HelloSign API evolved into the Dropbox Sign API,
this module was adapted to support the new API while retaining
the core functionality of the original HelloSign integration.


 * For a full description of the module, visit the project page:
   https://www.drupal.org/project/dropbox_sign

 * To submit bug reports and feature suggestions, or to track changes:
   https://www.drupal.org/project/issues/dropbox_sign

 * For more information about Dropbox Sign and its features:
   https://sign.dropbox.com/features

REQUIREMENTS
------------
This module requires the following library:
 * Dropbox Sign PHP SDK (https://github.com/hellosign/dropbox-sign-php)

This module requires the Encryption module as a dependency:
 * https://www.drupal.org/project/encryption

INSTALLATION
------------
 * Install this module via composer by running the following command:
   - composer require drupal/dropbox_sign

CONFIGURATION
-------------
 * Configure Dropbox Sign in Administration » Configuration » System »
  Dropbox Sign API or by going directly to /admin/config/system/dropbox-sign:

   - Dropbox Sign API Key

     The API key associated with your Dropbox Sign account. You can create an
     account at https://app.hellosign.com/account/signUp.

   - Dropbox Sign Client ID

     The Client ID associated with your Dropbox Sign project. After you have a
     Dropbox Sign account, you can create a client for the domain name you are
     using, and a client ID will be assigned to you.

   - CC email addresses

     A comma-separated list of email addresses which will be copied on every
     Dropbox Sign signature request. Useful if you want to track completed
     requests by email without manually adding an additional address to every
     signature request.

   - Test mode

     Enables and disables test mode. In test mode, all requests sent to
     Dropbox Sign will indicate that they are test requests.

USING THE API
-------------
 * To create a new Client connection instance, fetch the Dropbox Sign service
    and call getSignatureRequestApi().

   - $signatureApi = \Drupal::service('dropbox_sign')->getSignatureRequestApi().

 * To create a new signature request, call the createSignatureRequest method on
   the Dropbox Sign service with the following parameters:

   - $title: Document title
   - $subject: Email subject
   - $signers: Array of signers with a key of email address and a value of name
   - $file: A full path to a local system file
   - $mode: The type of signature request, either "embedded" or "email"
   - $redirectUrl: The redirected Url after the signature is made (Optional)
   - $msg = The email content to be sent for the signature request (Optional)

   If success, it returns a signature_request_id token from Dropbox Sign and an
   array of signatures. If failure, it returns an empty array.

 * To get the sign_url for the SignatureEmbedRequest, fetch the Dropbox Sign
   service and call getSignUrl().

    - $sign_url = \Drupal::service('dropbox_sign')->getSignUrl($signatureId);

 * To use any of the other methods the Dropbox Sign PHP requires, simply
   call those methods on the signatureApi.

   - Ex: $signatureApi->signatureRequestCancel($signature_request_id)

 * To include signature place holder inside custom templates,
    simply add this tag:

  - [sig|req|signer1] : represents a placeholder to the signature request
    of the first signer


MAINTAINERS
-----------
Current maintainers:
 * Elia Wehbe - [ewehbe](https://www.drupal.org/u/ewehbe)
 * Lara Zaki - [lara_z](https://www.drupal.org/u/lara_z) 

Supporting organizations:

 * bluedrop.fr - ebizproduction - [bluedrop.fr - ebizproduction](https://www.drupal.org/bluedropfr-ebizproduction)
