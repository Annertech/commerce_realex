<?php

namespace Drupal\commerce_realex\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Controller to handle payment failures.
 */
class PaymentFailure extends Controllerbase {

  /**
   * Route controller method.
   *
   * @todo ROAD-MAP:
   *   - get human-readable message from realex response
   *   - take action based on which realex response we get
   *   - invalid card details
   *   - handle other types of failure, more specific?
   */
  public function displayFailure($payable_item_id) {

    $this->paymentFailureTempStore = \Drupal::service('user.private_tempstore')->get('commerce_realex_failure');
    $payment = $this->paymentFailureTempStore->get('payment');
    $message = $payment->getMessage();
    $build = [];

    $url = Url::fromRoute('commerce_realex.payment_retry', ['payable_item_id' => $payable_item_id]);
    $add_link = Link::fromTextAndUrl($this->t('click here'), $url);
    $add_link = $add_link->toString();
    $block['message2'] = [
      '#type' => 'item',
      '#markup' => $this->t('Message from Payment provider') . '<br/><strong>' . $message . '</strong><br/>',
    ];
    $block['message'] = [
      '#type' => 'item',
      '#markup' => $this->t('Your payment was unsuccessful.') . '<br/>' . $this->t('Please @add_link to retry.', ['@add_link' => $add_link]),
    ];

    $build = [
      '#type' => 'container',
      'content' => [
        'content-wrapper' => [
          '#type' => 'container',
          'block' => $block,
          '#attributes' => array(
            'class' => array("realex-response"),
          ),
        ],
      ],
    ];
    return $build;
  }

  /**
   * Allow a payment to be retried.
   */
  public function retryPayment($payable_item_id) {
    try {
      $this->payableItemId = $payable_item_id;
      // @todo - Generalise when new payments come on board.
      $this->paymentTempStore = \Drupal::service('user.private_tempstore')->get('commerce_realex');
      $this->payableItem = $this->paymentTempStore->get($payable_item_id);
    }
    catch (\Exception $e) {
      \Drupal::logger('commerce_realex')->error($e->getMessage());
    }

    // Generated a new UUID - as Global Payment need each attempt to be unique.
    $uuid_service = \Drupal::service('uuid');
    $uuid = $uuid_service->generate();

    // Build temporary storage object from essential data.
    // @todo - the class shouldn't be hardcoded.
    $storage_data = [
      'class' => 'Drupal\commerce_realex\PayableItem',
      'values' => $this->payableItem['values'],
    ];

    // Save it to private temp store under the UUID "payment object" key.
    $this->paymentTempStore->set($uuid, $storage_data);

    return $this->redirect('commerce_realex.payment_form', ['payable_item_id' => $uuid]);
  }

}
