<?php

use Bitrix\Main\Loader;
use Bitrix\Iblock\Elements\ElementQrCodeForPaymentTable;
use Bitrix\Sale;

if (!Loader::includeModule('iblock')) {
    return;
}

class QrCode
{
    private $sumPriceBasket = 0;
    private $iblockId = 5;
    public function __construct(int $priceProduct = null)
    {
        $this->sumPriceBasket = $priceProduct;
    }

    public function getQrCode() {
        $qrCodes = $this->getQrCodes();

        if ($qrCodes[0]['date_update'] && date('Y-m-d', strtotime($qrCodes[0]['date_update'])) !== date('Y-m-d')) {
            $this->resetQrCodes($qrCodes);
            $qrCodes = $this->getQrCodes();
        }

        $currentQrCodes = $this->checkIncludeSumBasketInLimit($qrCodes);

        if($currentQrCodes != false) {
            return $currentQrCodes[0]["imgSrc"];
        }

        return false;
    }

    public function updateQrCode(\Bitrix\Main\Event $event)
    {
        $order = $event->getParameter("ENTITY");
        
        if ($order->getField("STATUS_ID") != "P") {
            return;
        }

        $orderPrice = $order->getPrice();
        $qrCodes = $this->getQrCodes();

        foreach ($qrCodes as $qrCode) {
            (int)$sumPaymentByQr =  $qrCode["sum"] + $orderPrice;
            if($sumPaymentByQr <= (int)$qrCode["limit"]) {
                CIBlockElement::SetPropertyValuesEx($qrCode["id"], $this->iblockId, array("PAYMENT_SUM_DAY" => $sumPaymentByQr));
                break;
            }
        }
    }

    private function resetQrCodes($qrCodes)
    {
        foreach ($qrCodes as $qrCode) {
            CIBlockElement::SetPropertyValuesEx($qrCode["id"], $this->iblockId, array("PAYMENT_SUM_DAY" => 0));

        }
    }

    private function getRandomQr($qrCodes)
    {
        return $qrCodes[array_rand($qrCodes)];
    }

    private function checkIncludeSumBasketInLimit(array $qrCodes) {
        $currentQr = array();

        if(!empty($qrCodes)) {
            foreach ($qrCodes as $qrCode) {
                (int)$sumPaymentByQr =  $qrCode["sum"] + $this->sumPriceBasket;
                if($sumPaymentByQr <= (int)$qrCode["limit"]) {
                    $currentQr[] = $qrCode;
                }
            }

            return $currentQr;
        }

        return false;
    }

    private function getQrCodes() {
        $elements = ElementQrCodeForPaymentTable::getList([
            'select' => [
                'ID',
                'NAME',
                'PREVIEW_PICTURE',
                'TIMESTAMP_X',
                'PAYMENT_DAY_LIMIT_' => 'PAYMENT_DAY_LIMIT',
                'PAYMENT_SUM_DAY_' => 'PAYMENT_SUM_DAY',
            ],
            'filter' => [
                'IBLOCK_ID' => $this->iblockId,
                'ACTIVE' => 'Y',
            ]
        ])->fetchAll();



        foreach ($elements as $element) {
            $qrCodes[] = array(
                "id" => $element["ID"],
                "limit" => $element["PAYMENT_DAY_LIMIT_VALUE"],
                "sum" => $element["PAYMENT_SUM_DAY_VALUE"],
                "imgSrc" => \CFile::GetPath($element["PREVIEW_PICTURE"]),
                "date_update" => $element["TIMESTAMP_X"]
            );
        }

        return $qrCodes;
    }
}