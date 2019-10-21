<?php

namespace Drupal\commerce_realex\PluginForm;

use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm;
use Drupal\commerce_order\Entity\Order;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Url;
use Drupal\commerce_realex\PayableItem;

/**
 * Realex Redirect checkout form.
 */
class RedirectCheckoutForm extends PaymentOffsiteForm {

  /**
   * Build the configuration form.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $payment = $this->entity;
    $order = $payment->getOrder();
    $profile = $order->getBillingProfile();
    $address = $order->getBillingProfile()->get('address')->first();
    $entity_type_manager = \Drupal::service('entity_type.manager');
    $currency_code = $payment->getAmount()->getCurrencyCode();
    $currency = $entity_type_manager->getStorage('commerce_currency')->load($currency_code);
    $currency_symbol = $currency->getSymbol();

    $plugin = $payment->getPaymentGateway()->getPlugin();

    $payable = new PayableItem();
    // Setup Custom Values on the Payable.
    $payable->setValue('payable_amount', $payment->getAmount()->getNumber());
    $payable->setValue('payable_currency', $currency_code);
    $payable->setValue('payable_currency_symbol', $currency_symbol);
    $payable->setValue('realex_config', $plugin->getConfiguration());

    // Customer Data
    $payable->setValue('given_name', $address->getGivenName());
    $payable->setValue('family_name', $address->getFamilyName());
    $payable->setValue('streetAddress1', $address->get('address_line1'));
    $payable->setValue('streetAddress2', $address->get('address_line2'));
    $payable->setValue('streetAddress3', $address->get('dependent_locality'));
    $payable->setValue('city', $address->get('locality'));
    $payable->setValue('postalCode', $address->get('postal_code'));
    $payable->setValue('country', $address->get('country_code'));
    $payable->setValue('commerce_order_id', $order->id());
    $payable->setValue('commerce_order_mail', $order->get('mail'));

    $data = [];
    $temp_store_key = $payable->saveTempStore();

    // Redirect to Payment Form.
    $url = Url::fromRoute('commerce_realex.payment_form', ['payable_item_id' => $temp_store_key]);

    return $this->buildRedirectForm(
      $form,
      $form_state,
      $url->toString(),
      $data,
      PaymentOffsiteForm::REDIRECT_POST
    );
  }

}
