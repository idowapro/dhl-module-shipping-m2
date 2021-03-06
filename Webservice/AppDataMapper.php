<?php
/**
 * Dhl Shipping
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to
 * newer versions in the future.
 *
 * PHP version 7
 *
 * @package   Dhl\Shipping\Webservice
 * @author    Christoph Aßmann <christoph.assmann@netresearch.de>
 * @copyright 2018 Netresearch GmbH & Co. KG
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      http://www.netresearch.de/
 */

namespace Dhl\Shipping\Webservice;

use Dhl\Shipping\Api\Data\ServiceInterface;
use Dhl\Shipping\Api\Data\ShippingInfoInterface;
use Dhl\Shipping\Api\OrderAddressExtensionRepositoryInterface;
use Dhl\Shipping\Api\ServicePoolInterface;
use Dhl\Shipping\Config\BcsConfigInterface;
use Dhl\Shipping\Config\GlConfigInterface;
use Dhl\Shipping\Model\Service\LabelServiceProvider;
use Dhl\Shipping\Model\Service\ServiceCollection;
use Dhl\Shipping\Service\Bcs\Insurance;
use Dhl\Shipping\Service\Gla\Insurance as InsuranceGla;
use Dhl\Shipping\Service\Bcs\PrintOnlyIfCodeable;
use Dhl\Shipping\Util\ShippingProducts\BcsShippingProductsInterface;
use Dhl\Shipping\Util\ShippingProducts\GlShippingProductsInterface;
use Dhl\Shipping\Util\ShippingProducts\ShippingProductsInterface;
use Dhl\Shipping\Util\StreetSplitterInterface;
use Dhl\Shipping\Webservice\RequestMapper\AppDataMapperInterface;
use Dhl\Shipping\Webservice\RequestType\CreateShipment\ShipmentOrder\Contact\AddressInterfaceFactory;
use Dhl\Shipping\Webservice\RequestType\CreateShipment\ShipmentOrder\Contact\IdCardInterfaceFactory;
use Dhl\Shipping\Webservice\RequestType\CreateShipment\ShipmentOrder\Contact\PackstationInterfaceFactory;
use Dhl\Shipping\Webservice\RequestType\CreateShipment\ShipmentOrder\Contact\ParcelShopInterfaceFactory;
use Dhl\Shipping\Webservice\RequestType\CreateShipment\ShipmentOrder\Contact\PostfilialeInterfaceFactory;
use Dhl\Shipping\Webservice\RequestType\CreateShipment\ShipmentOrder\Contact\ReceiverInterfaceFactory;
use Dhl\Shipping\Webservice\RequestType\CreateShipment\ShipmentOrder\Contact\ReturnReceiverInterfaceFactory;
use Dhl\Shipping\Webservice\RequestType\CreateShipment\ShipmentOrder\Contact\ShipperInterfaceFactory;
use Dhl\Shipping\Webservice\RequestType\CreateShipment\ShipmentOrder\Package\PackageItem;
use Dhl\Shipping\Webservice\RequestType\CreateShipment\ShipmentOrder\Package\PackageItemInterface;
use Dhl\Shipping\Webservice\RequestType\CreateShipment\ShipmentOrder\Package\PackageItemInterfaceFactory;
use Dhl\Shipping\Webservice\RequestType\CreateShipment\ShipmentOrder\PackageInterface;
use Dhl\Shipping\Webservice\RequestType\CreateShipment\ShipmentOrder\PackageInterfaceFactory;
use Dhl\Shipping\Webservice\RequestType\CreateShipment\ShipmentOrder\ShipmentDetails\BankDataInterfaceFactory;
use Dhl\Shipping\Webservice\RequestType\CreateShipment\ShipmentOrder\ShipmentDetails\ShipmentDetailsInterface;
use Dhl\Shipping\Webservice\RequestType\CreateShipment\ShipmentOrder\ShipmentDetails\ShipmentDetailsInterfaceFactory;
use Dhl\Shipping\Webservice\RequestType\CreateShipment\ShipmentOrderInterface;
use Dhl\Shipping\Webservice\RequestType\CreateShipment\ShipmentOrderInterfaceFactory;
use Dhl\Shipping\Webservice\RequestType\Generic\Package\DimensionsInterfaceFactory;
use Dhl\Shipping\Webservice\RequestType\Generic\Package\MonetaryValueInterfaceFactory;
use Dhl\Shipping\Webservice\RequestType\Generic\Package\WeightInterfaceFactory;
use Magento\Framework\DataObject;
use Magento\Shipping\Model\Shipment\Request as ShipmentRequest;

/**
 * AppDataMapper
 *
 * @package  Dhl\Shipping\Webservice
 * @author   Christoph Aßmann <christoph.assmann@netresearch.de>
 * @author   Max Melzer <max.melzer@netresearch.de>
 * @license  http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link     http://www.netresearch.de/
 */
class AppDataMapper implements AppDataMapperInterface
{
    /**
     * @var BcsConfigInterface
     */
    private $bcsConfig;

    /**
     * @var GlConfigInterface
     */
    private $glConfig;

    /**
     * @var ShippingProductsInterface|BcsShippingProductsInterface|GlShippingProductsInterface
     */
    private $shippingProducts;

    /**
     * @var StreetSplitterInterface
     */
    private $streetSplitter;

    /**
     * @var OrderAddressExtensionRepositoryInterface
     */
    private $addressExtensionRepository;

    /**
     * @var BankDataInterfaceFactory
     */
    private $bankDataFactory;

    /**
     * @var ShipmentDetailsInterfaceFactory
     */
    private $shipmentDetailsFactory;

    /**
     * @var AddressInterfaceFactory
     */
    private $addressFactory;

    /**
     * @var PackstationInterfaceFactory
     */
    private $packstationFactory;

    /**
     * @var PostfilialeInterfaceFactory
     */
    private $postfilialeFactory;

    /**
     * @var ParcelShopInterfaceFactory
     */
    private $parcelShopFactory;

    /**
     * @var IdCardInterfaceFactory
     */
    private $identityFactory;

    /**
     * @var ShipperInterfaceFactory
     */
    private $shipperFactory;

    /**
     * @var ReceiverInterfaceFactory
     */
    private $receiverFactory;

    /**
     * @var ReturnReceiverInterfaceFactory
     */
    private $returnReceiverFactory;

    /**
     * @var WeightInterfaceFactory
     */
    private $packageWeightFactory;

    /**
     * @var DimensionsInterfaceFactory
     */
    private $packageDimensionsFactory;

    /**
     * @var MonetaryValueInterfaceFactory
     */
    private $monetaryValueFactory;

    /**
     * @var PackageInterfaceFactory
     */
    private $packageFactory;

    /**
     * @var PackageItemInterfaceFactory
     */
    private $packageItemFactory;

    /**
     * @var ShipmentOrderInterfaceFactory
     */
    private $shipmentOrderFactory;

    /**
     * @var RequestValidatorInterface
     */
    private $requestValidator;

    /**
     * @var LabelServiceProvider
     */
    private $labelServiceProvider;

    /**
     * AppDataMapper constructor.
     *
     * @param BcsConfigInterface $bcsConfig
     * @param GlConfigInterface $glConfig
     * @param BcsShippingProductsInterface|GlShippingProductsInterface|ShippingProductsInterface $shippingProducts
     * @param StreetSplitterInterface $streetSplitter
     * @param OrderAddressExtensionRepositoryInterface $addressExtensionRepository
     * @param BankDataInterfaceFactory $bankDataFactory
     * @param ShipmentDetailsInterfaceFactory $shipmentDetailsFactory
     * @param AddressInterfaceFactory $addressFactory
     * @param PackstationInterfaceFactory $packstationFactory
     * @param PostfilialeInterfaceFactory $postfilialeFactory
     * @param ParcelShopInterfaceFactory $parcelShopFactory
     * @param IdCardInterfaceFactory $identityFactory
     * @param ShipperInterfaceFactory $shipperFactory
     * @param ReceiverInterfaceFactory $receiverFactory
     * @param ReturnReceiverInterfaceFactory $returnReceiverFactory
     * @param WeightInterfaceFactory $packageWeightFactory
     * @param DimensionsInterfaceFactory $packageDimensionsFactory
     * @param MonetaryValueInterfaceFactory $monetaryValueFactory
     * @param PackageInterfaceFactory $packageFactory
     * @param PackageItemInterfaceFactory $packageItemFactory
     * @param ShipmentOrderInterfaceFactory $shipmentOrderFactory
     * @param RequestValidatorInterface $requestValidator
     * @param LabelServiceProvider $labelServiceProvider
     */
    public function __construct(
        BcsConfigInterface $bcsConfig,
        GlConfigInterface $glConfig,
        ShippingProductsInterface $shippingProducts,
        StreetSplitterInterface $streetSplitter,
        OrderAddressExtensionRepositoryInterface $addressExtensionRepository,
        BankDataInterfaceFactory $bankDataFactory,
        ShipmentDetailsInterfaceFactory $shipmentDetailsFactory,
        AddressInterfaceFactory $addressFactory,
        PackstationInterfaceFactory $packstationFactory,
        PostfilialeInterfaceFactory $postfilialeFactory,
        ParcelShopInterfaceFactory $parcelShopFactory,
        IdCardInterfaceFactory $identityFactory,
        ShipperInterfaceFactory $shipperFactory,
        ReceiverInterfaceFactory $receiverFactory,
        ReturnReceiverInterfaceFactory $returnReceiverFactory,
        WeightInterfaceFactory $packageWeightFactory,
        DimensionsInterfaceFactory $packageDimensionsFactory,
        MonetaryValueInterfaceFactory $monetaryValueFactory,
        PackageInterfaceFactory $packageFactory,
        PackageItemInterfaceFactory $packageItemFactory,
        ShipmentOrderInterfaceFactory $shipmentOrderFactory,
        RequestValidatorInterface $requestValidator,
        LabelServiceProvider $labelServiceProvider
    ) {
        $this->bcsConfig = $bcsConfig;
        $this->glConfig = $glConfig;
        $this->shippingProducts = $shippingProducts;
        $this->streetSplitter = $streetSplitter;
        $this->addressExtensionRepository = $addressExtensionRepository;
        $this->bankDataFactory = $bankDataFactory;
        $this->shipmentDetailsFactory = $shipmentDetailsFactory;
        $this->addressFactory = $addressFactory;
        $this->packstationFactory = $packstationFactory;
        $this->postfilialeFactory = $postfilialeFactory;
        $this->parcelShopFactory = $parcelShopFactory;
        $this->identityFactory = $identityFactory;
        $this->shipperFactory = $shipperFactory;
        $this->receiverFactory = $receiverFactory;
        $this->returnReceiverFactory = $returnReceiverFactory;
        $this->packageWeightFactory = $packageWeightFactory;
        $this->packageDimensionsFactory = $packageDimensionsFactory;
        $this->monetaryValueFactory = $monetaryValueFactory;
        $this->packageFactory = $packageFactory;
        $this->packageItemFactory = $packageItemFactory;
        $this->shipmentOrderFactory = $shipmentOrderFactory;
        $this->requestValidator = $requestValidator;
        $this->labelServiceProvider = $labelServiceProvider;
    }

    /**
     * Calculate total value of order
     * FIXME(nr): handle partial shipments
     *
     * @param ShipmentRequest $request
     *
     * @return float
     */
    private function getOrderValue(ShipmentRequest $request)
    {
        return $request->getOrderShipment()->getOrder()->getBaseGrandTotal();
    }

    /**
     * @param ShipmentRequest $request
     * @param bool $printOnlyIfCodeable
     *
     * @return ShipmentDetailsInterface
     */
    private function getShipmentDetails(ShipmentRequest $request, $printOnlyIfCodeable)
    {
        $storeId  = $request->getOrderShipment()->getStoreId();
        $bankData = $this->bankDataFactory->create([
            'accountOwner'     => $this->bcsConfig->getBankDataAccountOwner($storeId),
            'bankName'         => $this->bcsConfig->getBankDataBankName($storeId),
            'iban'             => $this->bcsConfig->getBankDataIban($storeId),
            'bic'              => $this->bcsConfig->getBankDataBic($storeId),
            'notes'            => $this->bcsConfig->getBankDataNote($storeId),
            'accountReference' => $this->bcsConfig->getBankDataAccountReference($storeId),
        ]);
        $productCode = $request->getData('packaging_type');

        $ekp = $this->bcsConfig->getAccountEkp($storeId);
        $participations = $this->bcsConfig->getAccountParticipations($storeId);

        $billingNumber = $this->shippingProducts->getBillingNumber($productCode, $ekp, $participations);
        $returnBillingNumber = $this->shippingProducts->getReturnBillingNumber($productCode, $ekp, $participations);
        /**
         * Set timezone to MEZ to avoid landing on a date that is already yesterday in Germany.
         */
        /** @codingStandardsIgnoreLine */
        $shipmentDate = new \DateTime('now', new \DateTimeZone('Europe/Berlin'));

        $shipmentDetails = $this->shipmentDetailsFactory->create(
            [
                'isPrintOnlyIfCodeable' => $printOnlyIfCodeable,
                'isPartialShipment' => $this->isPartialShipment($request),
                'product' => $productCode,
                'accountNumber' => $billingNumber,
                'returnShipmentAccountNumber' => $returnBillingNumber,
                'pickupAccountNumber' => $this->glConfig->getPickupAccountNumber($storeId),
                'distributionCenter' => $this->glConfig->getDistributionCenter($storeId),
                'customerPrefix' => $this->glConfig->getCustomerPrefix($storeId),
                'consignmentNumber' => $this->glConfig->getConsignmentNumber($storeId),
                'reference' => $request->getOrderShipment()->getOrder()->getIncrementId(),
                'returnShipmentReference' => $request->getOrderShipment()->getOrder()->getIncrementId(),
                'shipmentDate' => $shipmentDate->format('Y-m-d'),
                'bankData' => $bankData,
            ]
        );

        return $shipmentDetails;
    }

    /**
     * @param ShipmentRequest $request
     *
     * @return \Dhl\Shipping\Webservice\RequestType\CreateShipment\ShipmentOrder\Contact\ShipperInterface
     */
    private function getShipper(ShipmentRequest $request)
    {
        $storeId      = $request->getOrderShipment()->getStoreId();
        $addressParts = $this->streetSplitter->splitStreet($request->getShipperAddressStreet());

        $address = $this->addressFactory->create([
            'street'                 => [$request->getShipperAddressStreet1(), $request->getShipperAddressStreet2()],
            'streetName'             => $addressParts['street_name'],
            'streetNumber'           => $addressParts['street_number'],
            'addressAddition'        => $addressParts['supplement'],
            'postalCode'             => $request->getShipperAddressPostalCode(),
            'city'                   => $request->getShipperAddressCity(),
            'state'                  => $request->getShipperAddressStateOrProvinceCode(),
            'countryCode'            => $request->getShipperAddressCountryCode(),
            'dispatchingInformation' => $this->bcsConfig->getDispatchingInformation($storeId)
        ]);

        $shipper = $this->shipperFactory->create([
            'companyName'   => $request->getShipperContactCompanyName(),
            'name'          => null,
            'nameAddition'  => $this->bcsConfig->getShipperCompanyAddition($storeId),
            'contactPerson' => $request->getShipperContactPersonName(),
            'phone'         => $request->getShipperContactPhoneNumber(),
            'email'         => $request->getData('shipper_email'),
            'address'       => $address,
        ]);

        return $shipper;
    }

    /**
     * @param ShipmentRequest $request
     *
     * @return \Dhl\Shipping\Webservice\RequestType\CreateShipment\ShipmentOrder\Contact\ReceiverInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getReceiver(ShipmentRequest $request)
    {
        $storeId = $request->getOrderShipment()->getStoreId();

        $addressId = $request->getOrderShipment()->getOrder()->getShippingAddress()->getEntityId();
        /** @var ShippingInfoInterface $shippingInfo */
        $shippingInfo = $this->addressExtensionRepository->getShippingInfo($addressId);
        if (!$shippingInfo) {
            $addressParts = $this->streetSplitter->splitStreet($request->getRecipientAddressStreet());
        } else {
            $addressParts = [
                'street_name' => $shippingInfo->getReceiver()->getAddress()->getStreetName(),
                'street_number' => $shippingInfo->getReceiver()->getAddress()->getStreetNumber(),
                'supplement' => $shippingInfo->getReceiver()->getAddress()->getAddressAddition(),
            ];
        }

        if ($shippingInfo && $shippingInfo->getReceiver()->getPackstation()) {
            $packstation = $shippingInfo->getReceiver()->getPackstation();
            $packstation = $this->packstationFactory->create([
                'packstationNumber' => $packstation->getPackstationNumber(),
                'zip' => $packstation->getZip(),
                'city' => $packstation->getCity(),
                'countryCode' => $packstation->getCountryISOCode(),
                'postNumber' => $packstation->getPostNumber(),
                'country' => $packstation->getCountry(),
                'state' => $packstation->getState(),
            ]);
        } else {
            $packstation = null;
        }

        if ($shippingInfo && $shippingInfo->getReceiver()->getPostfiliale()) {
            $postfiliale = $shippingInfo->getReceiver()->getPostfiliale();
            $postfiliale = $this->postfilialeFactory->create([
                'postfilialNumber' => $postfiliale->getPostfilialNumber(),
                'postNumber' => $postfiliale->getPostNumber(),
                'zip' => $postfiliale->getZip(),
                'city' => $postfiliale->getCity(),
                'countryCode' => $postfiliale->getCountryISOCode(),
                'country' => $postfiliale->getCountry(),
                'state' => $postfiliale->getState(),
            ]);
        } else {
            $postfiliale = null;
        }

        if ($shippingInfo && $shippingInfo->getReceiver()->getParcelShop()) {
            $parcelShop = $shippingInfo->getReceiver()->getParcelShop();
            $parcelShop = $this->parcelShopFactory->create([
                'parcelShopNumber' => $parcelShop->getParcelShopNumber(),
                'zip' => $parcelShop->getZip(),
                'city' => $parcelShop->getCity(),
                'countryCode' => $parcelShop->getCountryISOCode(),
                'streetName' => $parcelShop->getStreetName(),
                'streetNumber' => $parcelShop->getStreetNumber(),
                'country' => $parcelShop->getCountry(),
                'state' => $parcelShop->getState()
            ]);
        } else {
            $parcelShop = null;
        }

        $addressStreet = [$request->getRecipientAddressStreet1(), $request->getRecipientAddressStreet2()];
        $address = $this->addressFactory->create([
            'street'                 => $addressStreet,
            'streetName'             => $addressParts['street_name'],
            'streetNumber'           => $addressParts['street_number'],
            'addressAddition'        => $addressParts['supplement'],
            'postalCode'             => $request->getRecipientAddressPostalCode(),
            'city'                   => $request->getRecipientAddressCity(),
            'state'                  => $request->getRecipientAddressStateOrProvinceCode(),
            'countryCode'            => $request->getRecipientAddressCountryCode(),
            'dispatchingInformation' => $this->bcsConfig->getDispatchingInformation($storeId)
        ]);

        $idCard = $this->identityFactory->create([
            'type' => '',
            'number' => '',
        ]);

        $receiver = $this->receiverFactory->create([
            'companyName'   => $request->getRecipientContactCompanyName(),
            'name'          => $request->getRecipientContactPersonName(),
            'nameAddition'  => null,
            'contactPerson' => $request->getRecipientContactPersonName(),
            'phone'         => $request->getRecipientContactPhoneNumber(),
            'email'         => $request->getData('recipient_email'),
            'address'       => $address,
            'identity'      => $idCard,
            'packstation'   => $packstation,
            'postfiliale'   => $postfiliale,
            'parcelShop'   =>  $parcelShop,
        ]);

        return $receiver;
    }

    /**
     * TODO(nr): allow other return receiver than shipping origin.
     *
     * @param ShipmentRequest $request
     *
     * @return \Dhl\Shipping\Webservice\RequestType\CreateShipment\ShipmentOrder\Contact\ReturnReceiverInterface
     */
    private function getReturnReceiver(ShipmentRequest $request)
    {
        $storeId      = $request->getOrderShipment()->getStoreId();
        $addressParts = $this->streetSplitter->splitStreet($request->getShipperAddressStreet());

        $address = $this->addressFactory->create([
            'street'                 => [$request->getShipperAddressStreet1(), $request->getShipperAddressStreet2()],
            'streetName'             => $addressParts['street_name'],
            'streetNumber'           => $addressParts['street_number'],
            'addressAddition'        => $addressParts['supplement'],
            'postalCode'             => $request->getShipperAddressPostalCode(),
            'city'                   => $request->getShipperAddressCity(),
            'state'                  => $request->getShipperAddressStateOrProvinceCode(),
            'countryCode'            => $request->getShipperAddressCountryCode(),
            'dispatchingInformation' => $this->bcsConfig->getDispatchingInformation($storeId)
        ]);

        $returnReceiver = $this->returnReceiverFactory->create([
            'companyName'   => $request->getShipperContactCompanyName(),
            'name'          => null,
            'nameAddition'  => $this->bcsConfig->getShipperCompanyAddition($storeId),
            'contactPerson' => $request->getShipperContactPersonName(),
            'phone'         => $request->getShipperContactPhoneNumber(),
            'email'         => $request->getData('shipper_email'),
            'address'       => $address,
        ]);

        return $returnReceiver;
    }

    /**
     * @param ShipmentRequest $request
     *
     * @return ServiceInterface[]|ServiceCollection
     */
    private function getServices(ShipmentRequest $request)
    {
        $packageParams = $request->getData('package_params');
        $servicesData  = $packageParams->getData('services') ?: [];

        if (isset($servicesData[Insurance::CODE]) || isset($servicesData[InsuranceGla::CODE])) {
            /** Normalize Insurance service from "bcs/gla_insurance" to "insurance" */
            unset($servicesData[Insurance::CODE], $servicesData[InsuranceGla::CODE]);
            $servicesData[ServicePoolInterface::SERVICE_INSURANCE_CODE] = [
                Insurance::PROPERTY_AMOUNT => $this->getOrderValue($request),
                Insurance::PROPERTY_CURRENCY_CODE => $request->getData('base_currency_code')
            ];
        }

        $services = $this->labelServiceProvider->getServices($servicesData, $request->getOrderShipment());

        return $services;
    }

    /**
     * @param ShipmentRequest $request
     * @return PackageItemInterface[]
     */
    private function getPackageItems(ShipmentRequest $request)
    {
        $packageItems = [];
        foreach ($request->getData('package_items') as $item) {
            $itemObject = new \Magento\Framework\DataObject($item);
            /** @var PackageItem $packageItem */
            $itemWeight = $this->packageWeightFactory->create([
                'value' => $itemObject->getData('weight') ? : 0,
                'unitOfMeasurement' => $request->getData('package_params')->getData('weight_units'),
            ]);

            $itemCustomsValue = $this->monetaryValueFactory->create([
                'value' => $itemObject->getData('customs_value'),
                'currencyCode' => $request->getData('base_currency_code')
            ]);

            $itemPrice = $this->monetaryValueFactory->create([
                'value' => $itemObject->getData('price'),
                'currencyCode' => $request->getData('base_currency_code')
            ]);

            $packageItem = $this->packageItemFactory->create([
                'qty' => $itemObject->getData('qty'),
                'customsValue' => $itemCustomsValue,
                'customsItemDescription' => substr($itemObject->getData('customs_item_description'), 0, 50),
                'price' => $itemPrice,
                'name' => $itemObject->getData('name'),
                'weight' => $itemWeight,
                'productId' => $itemObject->getData('product_id'),
                'orderItemId' => $itemObject->getData('order_item_id'),
                'tariffNumber' => $itemObject->getData('tariff_number'),
                'itemOriginCountry' => $itemObject->getData('item_origin_country'),
                'sku' => $itemObject->getData('sku')
            ]);
            $packageItems[] = $packageItem;
        }
        return $packageItems;
    }

    /**
     * @param ShipmentRequest $request
     *
     * @return PackageInterface
     */
    private function getPackage(ShipmentRequest $request)
    {
        $packageId = $request->getData('package_id');

        /** @var DataObject $packageParams */
        $packageParams = $request->getData('package_params');
        $customsData = $packageParams->getData('customs') ?: [];
        $packageCustoms = new DataObject($customsData);

        $packageItems = $this->getPackageItems($request);

        $weight = $this->packageWeightFactory->create(
            [
                'value' => $packageParams->getData('weight'),
                'unitOfMeasurement' => $packageParams->getData('weight_units'),
            ]
        );
        $dimensions = $this->packageDimensionsFactory->create(
            [
                'length' => $packageParams->getData('length'),
                'width' => $packageParams->getData('width'),
                'height' => $packageParams->getData('height'),
                'unitOfMeasurement' => $packageParams->getData('dimension_units'),
            ]
        );

        $packageValue = array_reduce(
            $request->getData('package_items'),
            function (
                $carry,
                $item
            ) {
                $value = isset($item['customs_value']) ? $item['customs_value'] : $item['price'];

                $price = $value * 1000;
                $carry += ($price * $item['qty']);

                return $carry;
            }
        );
        $packageValue = number_format(
            $packageValue / 1000,
            2,
            '.',
            ''
        );

        //FIXME(nr): should declared value include tax?
        $declaredValue = $this->monetaryValueFactory->create(
            [
                'value' => $packageValue,
                'currencyCode' => $request->getData('base_currency_code'),
            ]
        );

        $additionalFee = $this->monetaryValueFactory->create(
            [
                'value' => $packageCustoms->getData('additional_fee') ?: 0,
                'currencyCode' => $request->getData('base_currency_code'),
            ]
        );
        $package = $this->packageFactory->create(
            [
                'packageId' => $packageId,
                'weight' => $weight,
                'dimensions' => $dimensions,
                'declaredValue' => $declaredValue,
                'additionalFee' => $additionalFee,
                'exportType' => $packageParams->getData('content_type'),
                'exportTypeDescription' => $packageParams->getData('content_type_other'),
                'termsOfTrade' => $packageCustoms->getData('terms_of_trade'),
                'placeOfCommittal' => $packageCustoms->getData('place_of_commital'),
                'permitNumber' => $packageCustoms->getData('permit_number'),
                'attestationNumber' => $packageCustoms->getData('attestation_number'),
                'exportNotification' => (bool)$packageCustoms->getData('export_notification'),
                'dgCategory' => $packageCustoms->getData('dangerous_goods_category'),
                'exportDescription' => $packageCustoms->getData('export_description'),
                'items' => $packageItems,
            ]
        );

        return $package;
    }

    /**
     * Convert M2 shipment request to platform independent request object.
     *
     * @param ShipmentRequest $request
     * @param string          $sequenceNumber
     *
     * @return ShipmentOrderInterface
     * @throws \Dhl\Shipping\Webservice\Exception\CreateShipmentValidationException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function mapShipmentRequest($request, $sequenceNumber)
    {
        $services        = $this->getServices($request);
        $printOnlyIfCodeable = false;
        foreach ($services as $service) {
            if ($service->getCode() === PrintOnlyIfCodeable::CODE) {
                $printOnlyIfCodeable = $service->isSelected();
                break;
            }
        }
        $shipmentDetails = $this->getShipmentDetails($request, $printOnlyIfCodeable);
        $shipper         = $this->getShipper($request);
        $receiver        = $this->getReceiver($request);
        $returnReceiver  = $this->getReturnReceiver($request);
        $package         = $this->getPackage($request);

        $shipmentOrder = $this->shipmentOrderFactory->create([
            'sequenceNumber'  => $sequenceNumber,
            'shipmentDetails' => $shipmentDetails,
            'shipper'         => $shipper,
            'receiver'        => $receiver,
            'returnReceiver'  => $returnReceiver,
            'services'        => $services,
            'packages'        => [$package],
        ]);

        $shipmentOrder = $this->requestValidator->validateShipmentOrder($shipmentOrder);
        return $shipmentOrder;
    }

    /**
     * @param ShipmentRequest $request
     * @return bool
     */
    private function isPartialShipment(ShipmentRequest $request)
    {
        $qtyOrdered = $request->getOrderShipment()->getOrder()->getTotalQtyOrdered();
        $qtyShipped = $request->getOrderShipment()->getTotalQty();
        return ($qtyOrdered != $qtyShipped) || (count($request->getData('packages')) > 1);
    }
}
