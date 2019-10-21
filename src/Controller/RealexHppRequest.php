<?php

namespace Drupal\commerce_realex\Controller;

// use com\realexpayments\hpp\sdk\domain\HppRequest;
// use com\realexpayments\hpp\sdk\RealexHpp;
// use com\realexpayments\hpp\sdk\RealexValidationException;
// use com\realexpayments\hpp\sdk\RealexException;

// require_once (drupal_get_path('module', 'commerce_realex') . '/vendor/autoload.php');
use GlobalPayments\Api\PaymentMethods\CreditCardData;
use GlobalPayments\Api\ServicesConfig;
use GlobalPayments\Api\ServicesContainer;
use GlobalPayments\Api\HostedPaymentConfig;
use GlobalPayments\Api\Entities\Address;
use GlobalPayments\Api\Entities\HostedPaymentData;
use GlobalPayments\Api\Entities\Enums\HppVersion;
use GlobalPayments\Api\Entities\Enums\AddressType;
use GlobalPayments\Api\Services\HostedService;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Realex Hosted Payment Page (HPP) Request controller.
 */
class RealexHppRequest extends ControllerBase {

  /**
   * @var \Drupal\user\PrivateTempStore
   */
  protected $paymentTempStore;

  /**
   * @var Drupal\commerce_realex\PayableItemInterface
   */
  protected $payableItem;

  /**
   * @var string
   */
  protected $payableItemId;

  /**
   * Build a Realex HPP request JSON object.
   */
  public function buildJson($payable_item_id) {

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
    $supplementary_data = [
      'temporary_payable_item_id' => $this->payableItemId,
      'HPP_CUSTOMER_FIRSTNAME' => $this->payableItem->getValue('given_name'),
      'HPP_CUSTOMER_LASTNAME' => $this->payableItem->getValue('family_name'),
    ];

    // Configure client, request and HPP settings

    $config = new ServicesConfig();
    $config->merchantId = $realex_config['realex_merchant_id'];
    $config->accountId = $realex_config['realex_account'];
    $config->sharedSecret = $realex_config['realex_shared_secret'];
    // @todo check config for sandbox or production.
    $config->serviceUrl = $realex_config['realex_server_url']; // "https://pay.sandbox.realexpayments.com/pay";

    $config->hostedPaymentConfig = new HostedPaymentConfig();
    $config->hostedPaymentConfig->version = HppVersion::VERSION_2;
    $service = new HostedService($config);

    // Add 3D Secure 2 Mandatory and Recommended Fields
    $hostedPaymentData = new HostedPaymentData();
    $hostedPaymentData->customerEmail = $this->payableItem->getValue('commerce_order_mail')->getString();
    // Making the assumption that we don't have a phone number as it is often not captured.
    $hostedPaymentData->customerPhoneMobile = "";
    $hostedPaymentData->supplementaryData = addslashes(json_encode($supplementary_data));;
    $hostedPaymentData->addressesMatch = false;

    // Realex requires a numeric country code. The order has an alpha-2 code.
    $alpha_country_code = $this->payableItem->getValue('country')->getString();
    $numeric_country_code = $this->get_country_numeric_code($alpha_country_code);

    $billingAddress = new Address();
    $billingAddress->streetAddress1 = $this->payableItem->getValue('streetAddress1')->getString();
    $billingAddress->streetAddress2 = $this->payableItem->getValue('streetAddress2')->getString();
    $billingAddress->streetAddress3 = $this->payableItem->getValue('streetAddress3')->getString();
    $billingAddress->city = $this->payableItem->getValue('city')->getString();
    $billingAddress->postalCode = $this->payableItem->getValue('postalCode')->getString();
    $billingAddress->country = $numeric_country_code;

    // Shipping address is optional so not required.

    $payable_amount = $this->payableItem->getPayableAmount();

    try {
      $hppJson = $service->charge($payable_amount)
        ->withCurrency("EUR")
        ->withHostedPaymentData($hostedPaymentData)
        ->withAddress($billingAddress, AddressType::BILLING)
        ->serialize();

      $response = new Response();
      $response->setContent($hppJson);
      $response->headers->set('Content-Type', 'application/json');
      return $response;
    }
    catch (RealexValidationException $e) {
      \Drupal::logger('commerce_realex')->error($e->getMessage());
    }
    catch (RealexException $e) {
      \Drupal::logger('commerce_realex')->error($e->getMessage());
    }
    catch (\Exception $e) {
      \Drupal::logger('commerce_realex')->error($e->getMessage());
    }

  }

  /**
   * Returns a numeric country code.
   *
   * @return integer
   *   A numeric country code.
   *
   * @param $code
   *   A string value alpha-2 code.
   */
  public function get_country_numeric_code($code) {
    $country_codes = array(
      'AF' => '004',
      'AX' => '248',
      'AL' => '008',
      'DZ' => '012',
      'AS' => '016',
      'AD' => '020',
      'AO' => '024',
      'AI' => '660',
      'AQ' => '010',
      'AG' => '028',
      'AR' => '032',
      'AM' => '051',
      'AW' => '533',
      'AU' => '036',
      'AT' => '040',
      'AZ' => '031',
      'BS' => '044',
      'BH' => '048',
      'BD' => '050',
      'BB' => '052',
      'BY' => '112',
      'BE' => '056',
      'BZ' => '084',
      'BJ' => '204',
      'BM' => '060',
      'BT' => '064',
      'BO' => '068',
      'BQ' => '535',
      'BA' => '070',
      'BW' => '072',
      'BV' => '074',
      'BR' => '076',
      'IO' => '086',
      'BN' => '096',
      'BG' => '100',
      'BF' => '854',
      'BI' => '108',
      'CV' => '132',
      'KH' => '116',
      'CM' => '120',
      'CA' => '124',
      'KY' => '136',
      'CF' => '140',
      'TD' => '148',
      'CL' => '152',
      'CN' => '156',
      'CX' => '162',
      'CC' => '166',
      'CO' => '170',
      'KM' => '174',
      'CG' => '178',
      'CD' => '180',
      'CK' => '184',
      'CR' => '188',
      'CI' => '384',
      'HR' => '191',
      'CU' => '192',
      'CW' => '531',
      'CY' => '196',
      'CZ' => '203',
      'DK' => '208',
      'DJ' => '262',
      'DM' => '212',
      'DO' => '214',
      'EC' => '218',
      'EG' => '818',
      'SV' => '222',
      'GQ' => '226',
      'ER' => '232',
      'EE' => '233',
      'ET' => '231',
      'SZ' => '748',
      'FK' => '238',
      'FO' => '234',
      'FJ' => '242',
      'FI' => '246',
      'FR' => '250',
      'GF' => '254',
      'PF' => '258',
      'TF' => '260',
      'GA' => '266',
      'GM' => '270',
      'GE' => '268',
      'DE' => '276',
      'GH' => '288',
      'GI' => '292',
      'GR' => '300',
      'GL' => '304',
      'GD' => '308',
      'GP' => '312',
      'GU' => '316',
      'GT' => '320',
      'GG' => '831',
      'GN' => '324',
      'GW' => '624',
      'GY' => '328',
      'HT' => '332',
      'HM' => '334',
      'VA' => '336',
      'HN' => '340',
      'HK' => '344',
      'HU' => '348',
      'IS' => '352',
      'IN' => '356',
      'ID' => '360',
      'IR' => '364',
      'IQ' => '368',
      'IE' => '372',
      'IM' => '833',
      'IL' => '376',
      'IT' => '380',
      'JM' => '388',
      'JP' => '392',
      'JE' => '832',
      'JO' => '400',
      'KZ' => '398',
      'KE' => '404',
      'KI' => '296',
      'KP' => '408',
      'KR' => '410',
      'KW' => '414',
      'KG' => '417',
      'LA' => '418',
      'LV' => '428',
      'LB' => '422',
      'LS' => '426',
      'LR' => '430',
      'LY' => '434',
      'LI' => '438',
      'LT' => '440',
      'LU' => '442',
      'MO' => '446',
      'MK' => '807',
      'MG' => '450',
      'MW' => '454',
      'MY' => '458',
      'MV' => '462',
      'ML' => '466',
      'MT' => '470',
      'MH' => '584',
      'MQ' => '474',
      'MR' => '478',
      'MU' => '480',
      'YT' => '175',
      'MX' => '484',
      'FM' => '583',
      'MD' => '498',
      'MC' => '492',
      'MN' => '496',
      'ME' => '499',
      'MS' => '500',
      'MA' => '504',
      'MZ' => '508',
      'MM' => '104',
      'NA' => '516',
      'NR' => '520',
      'NP' => '524',
      'NL' => '528',
      'NC' => '540',
      'NZ' => '554',
      'NI' => '558',
      'NE' => '562',
      'NG' => '566',
      'NU' => '570',
      'NF' => '574',
      'MP' => '580',
      'NO' => '578',
      'OM' => '512',
      'PK' => '586',
      'PW' => '585',
      'PS' => '275',
      'PA' => '591',
      'PG' => '598',
      'PY' => '600',
      'PE' => '604',
      'PH' => '608',
      'PN' => '612',
      'PL' => '616',
      'PT' => '620',
      'PR' => '630',
      'QA' => '634',
      'RE' => '638',
      'RO' => '642',
      'RU' => '643',
      'RW' => '646',
      'BL' => '652',
      'SH' => '654',
      'KN' => '659',
      'LC' => '662',
      'MF' => '663',
      'PM' => '666',
      'VC' => '670',
      'WS' => '882',
      'SM' => '674',
      'ST' => '678',
      'SA' => '682',
      'SN' => '686',
      'RS' => '688',
      'SC' => '690',
      'SL' => '694',
      'SG' => '702',
      'SX' => '534',
      'SK' => '703',
      'SI' => '705',
      'SB' => '090',
      'SO' => '706',
      'ZA' => '710',
      'GS' => '239',
      'SS' => '728',
      'ES' => '724',
      'LK' => '144',
      'SD' => '729',
      'SR' => '740',
      'SJ' => '744',
      'SE' => '752',
      'CH' => '756',
      'SY' => '760',
      'TW' => '158',
      'TJ' => '762',
      'TZ' => '834',
      'TH' => '764',
      'TL' => '626',
      'TG' => '768',
      'TK' => '772',
      'TO' => '776',
      'TT' => '780',
      'TN' => '788',
      'TR' => '792',
      'TM' => '795',
      'TC' => '796',
      'TV' => '798',
      'UG' => '800',
      'UA' => '804',
      'AE' => '784',
      'GB' => '826',
      'US' => '840',
      'UM' => '581',
      'UY' => '858',
      'UZ' => '860',
      'VU' => '548',
      'VE' => '862',
      'VN' => '704',
      'VG' => '092',
      'VI' => '850',
      'WF' => '876',
      'EH' => '732',
      'YE' => '887',
      'ZM' => '894',
      'ZW' => '716',
    );
    return $country_codes[$code];
  }
}
