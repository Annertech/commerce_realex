<?php

namespace Drupal\commerce_realex\Controller;

// use com\realexpayments\hpp\sdk\domain\HppRequest;
// use com\realexpayments\hpp\sdk\RealexHpp;
// use com\realexpayments\hpp\sdk\RealexValidationException;
// use com\realexpayments\hpp\sdk\RealexException;

// require_once (drupal_get_path('module', 'commerce_realex') . '/vendor/autoload.php');

use GlobalPayments\Api\ServicesConfig;
use GlobalPayments\Api\Services\HostedService;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use Drupal\Core\Controller\ControllerBase;
// @todo ROAD-MAP use CrmPayableItemInterface formally
// use Drupal\commerce_realex\CrmPayableItemInterface;
use Drupal\commerce_realex\CrmPaymentRecord;

class RealexHppResponse extends ControllerBase {

  /**
   * @var \Drupal\user\PrivateTempStore
   */
  protected $paymentTempStore;

  /**
   * @var PayableItemInterface
   */
  protected $payableItem;

  /**
   * @var string
   */
  protected $payableItemId;

  /**
   * The parsed response from Global Payments.
   *
   * @var com\globalpayments\hpp\sdk\domain\HppResponse
   */
  protected $hppResponse;

  /**
   * Process a HPP payment respnse from Global Payments.
   */
  public function processResponse($payable_item_id) {

    try {
      $this->payableItemId = $payable_item_id;
      $this->paymentTempStore = \Drupal::service('user.private_tempstore')->get('commerce_realex');
      $payable_item_class = $this->paymentTempStore->get($payable_item_id)['class'];
      $this->payableItem = $payable_item_class::createFromPaymentTempStore($payable_item_id);
    }
    catch (\Exception $e) {
      \Drupal::logger('commerce_realex')->error($e->getMessage());
    }
    $realex_config = $this->payableItem->getValue('realex_config');

    // Configure client settings.
    $config = new ServicesConfig();
    $config->merchantId = $realex_config['realex_merchant_id'];
    $config->accountId = $realex_config['realex_account'];
    $config->sharedSecret = $realex_config['realex_shared_secret'];
    $config->serviceUrl = "https://pay.sandbox.realexpayments.com/pay";

    $service = new HostedService($config);

    try {
      // create the response object from the response JSON
      $responseJson = $_POST['hppResponse'];
      $parsedResponse = $service->parseResponse($responseJson, true);

      $orderId = $parsedResponse->orderId; // GTI5Yxb0SumL_TkDMCAxQA
      $result = $parsedResponse->responseCode; // 00
      $realex_message = $parsedResponse->responseMessage; // [ test system ] Authorised
      $responseValues = $parsedResponse->responseValues; // get values accessible by key
      $pasRef = $parsedResponse->responseValues['PASREF'];
      $clean_data = stripslashes($responseValues['SUPPLEMENTARY_DATA']);
      $supplementary_data = json_decode($clean_data);
  }
    catch (RealexValidationException $e) {
      return $e->getMessage();
    }
    catch (RealexException $e) {
      return $e->getMessage();
    }

    // Get payable Item ID out of response supplementary data.
    // We sent this to Global Payments in the request JSON, expect to get it
    // back.
    $this->payableItemId = $supplementary_data['temporary_payable_item_id'];

    // Retrieve object representing temporary record from paymentTempStore.
    $payable_item_class = $this->paymentTempStore->get($this->payableItemId)['class'];
    $this->payableItem = $payable_item_class::createFromPaymentTempStore($this->payableItemId);

    // Check stuff is OK.
    if ($result == '00') {

      // Display a message.
      // @todo - Allow user to override this.
      $currency_formatter = \Drupal::service('commerce_price.currency_formatter');
      $payment_amount_formatted = $currency_formatter->format($this->payableItem->getValue('payable_amount'), $this->payableItem->getValue('payable_currency'));
      $display_message = $this->t('Thank you for your payment of @payment_amount.', ['@payment_amount' => $payment_amount_formatted]);
      drupal_set_message($display_message);

      // Update PayableItem in tempstore.
      $this->payableItem->setValue('payment_complete', TRUE);
      $this->payableItem->setValue('authCode', $authCode);
      $this->payableItem->setValue('message', $realex_message);
      $this->payableItem->saveTempStore($this->payableItemId);

      // Redirect the user to the "Successful Payment" callback.
      $success_callback = 'commerce_payment.checkout.return';
      return $this->redirect($success_callback,
        [
          'commerce_order' => $this->payableItem->getValue('commerce_order_id'),
          'step' => 'payment',
        ],
        ['query' => ['payable_item_id' => $this->payableItemId]]
      );
    }

    // Otherwise something went wrong with payment.
    else {
      // @todo tell the user the outcome!
      // Get human-readable message from realex response.
      // Take action based on which realex response we get.
      // - invalid card details
      // - set message, redirect to payment form to attempt again?
      // - handle other types of failure, more specific?
      // Fallback message if no other remedial action has already been taken.
      // Store the response and redirect to callback.
      $this->paymentFailureTempStore = \Drupal::service('user.private_tempstore')->get('commerce_realex_failure');
      $this->paymentFailureTempStore->set('payment', $parsedResponse);
      return $this->redirect('commerce_realex.payment_failure',
        ['payable_item_id' => $this->payableItemId]
      );
    }

  }

}
