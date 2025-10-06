<?php

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Sale;

Loader::IncludeModule("catalog");
Loader::IncludeModule("sale");
CModule::IncludeModule("iblock");

$request = Application::getInstance()->getContext()->getRequest();
$post = $request->getPost("data");
$data = json_decode($post, true);

$basket = Sale\Basket::loadItemsForFUser(Sale\Fuser::getId(), Bitrix\Main\Context::getCurrent()->getSite());

if(isset($data["productId"]) && isset($data["quantity"])) {
    $productId = intval($data["productId"]);
    $quantity = intval($data["quantity"]);
}

switch ($data["action"]) {
    case "add":
        $item = $basket->createItem('catalog', $productId);
        $item->setFields(array(
            'QUANTITY' => $quantity,
            'CURRENCY' => Bitrix\Currency\CurrencyManager::getBaseCurrency(),
            'LID' => Bitrix\Main\Context::getCurrent()->getSite(),
            'PRODUCT_PROVIDER_CLASS' => 'CCatalogProductProvider',
        ));
        $basket->save();

        break;
    case "reload":
        $item = $basket->getExistsItem('catalog', $productId);
        if($quantity <= 0) {
            $item->delete();
            $basket->save();
        } elseif ($item) {
            $item->setField('QUANTITY',  $quantity);
        } else {
            $item = $basket->createItem('catalog', $productId);
            $item->setFields(array(
                'QUANTITY' => $quantity,
                'CURRENCY' => Bitrix\Currency\CurrencyManager::getBaseCurrency(),
                'LID' => Bitrix\Main\Context::getCurrent()->getSite(),
                'PRODUCT_PROVIDER_CLASS' => 'CCatalogProductProvider',
            ));
        }

        $basket->save();
        break;
    case "getInBasketProductId":
        $productIds = array();

        foreach ($basket as $basketItem) {
            $productIds[$basketItem->getProductId()] = $basketItem->getQuantity();
        }
        print_r(json_encode($productIds));
        break;
    case "removeFromBasketPage":
        $item = $basket->getExistsItem('catalog', $productId);
        if($item) {
            $item->delete();
            $basket->save();
        }
        break;
    case "replace":
        $itemReplace = $basket->getExistsItem('catalog', $data["replaceProductId"]);
        $itemReplace->delete();

        $item = $basket->getExistsItem('catalog', $productId);
        $item = $basket->createItem('catalog', $productId);
        $item->setFields(array(
            'QUANTITY' => $quantity,
            'CURRENCY' => Bitrix\Currency\CurrencyManager::getBaseCurrency(),
            'LID' => Bitrix\Main\Context::getCurrent()->getSite(),
            'PRODUCT_PROVIDER_CLASS' => 'CCatalogProductProvider',
        ));
        $basket->save();

        break;

    case "update_personal_data":

        $user = new CUser;

        $arFields = Array(
            "NAME" => $data["name"] ? $data["name"] : '',
            "LAST_NAME" => $data["surname"] ? $data["surname"] : '',
            "PERSONAL_CITY" => $data["city"] ? $data["city"] : '',
            "WORK_COMPANY" => $data["company"] ? $data["company"] : '',
            "PERSONAL_STREET" => $data["address"] ? $data["address"] : '',
            "PERSONAL_PHONE" => $data["phone"] ? $data["phone"] : '',
            "PERSONAL_MAILBOX" => $data["email"] ? $data["email"] : '',
            "PASSWORD" => $data["password"] ? $data["password"] : '',
            "CONFIRM_PASSWORD"  => $data["password_confirmation"] ? $data["password_confirmation"] : '',
        );

        if($user->Update($USER->GetID(), $arFields)){
            echo json_encode(array("result" => "success"));
        } else {
            echo json_encode(array("result" => "error","text_errors"=>$user->LAST_ERROR));
        }

        break;
    case "resetBasket":
        $fuserId = Bitrix\Sale\Fuser::getId();
        $siteId = Bitrix\Main\Context::getCurrent()->getSite();

        $basket = Bitrix\Sale\Basket::loadItemsForFUser($fuserId, $siteId);

        foreach ($basket as $basketItem) {
            $basketItem->delete();
        }

        $basket->save();

        break;
    case "refreshBasketPrice":
        $oldPrice = CurrencyFormat((int) $basket->getBasePrice(), "RUB");
        $newPrice = CurrencyFormat((int) $basket->getPrice(), "RUB");

        if((int) $oldPrice == (int) $newPrice){
            $oldPrice = '';
        }

        echo json_encode(array("newPrice" => $newPrice, "oldPrice" => $oldPrice));
        break;

}

if($data["action"] !== "getInBasketProductId" && $data["action"] !== "removeFromBasketPage" && $data["action"] !== "refreshBasketPrice") {
    $APPLICATION->IncludeComponent(
        "bitrix:sale.basket.basket.line",
        "header_basket",
        [
            "HIDE_ON_BASKET_PAGES" => "Y",
            "PATH_TO_BASKET" => "/basket/",
            "PATH_TO_ORDER" => SITE_DIR . "personal/order/make/",
            "PATH_TO_PERSONAL" => SITE_DIR . "personal/",
            "PATH_TO_PROFILE" => SITE_DIR . "personal/",
            "PATH_TO_REGISTER" => SITE_DIR . "login/",
            "POSITION_FIXED" => "Y",
            "POSITION_HORIZONTAL" => "right",
            "POSITION_VERTICAL" => "top",
            "SHOW_AUTHOR" => "N",
            "SHOW_DELAY" => "N",
            "SHOW_EMPTY_VALUES" => "Y",
            "SHOW_IMAGE" => "N",
            "SHOW_NOTAVAIL" => "N",
            "SHOW_NUM_PRODUCTS" => "Y",
            "SHOW_PERSONAL_LINK" => "N",
            "SHOW_PRICE" => "Y",
            "SHOW_PRODUCTS" => "Y",
            "SHOW_SUMMARY" => "N",
            "SHOW_TOTAL_PRICE" => "Y",
            "SHOW_LIST" => "N"
        ],
        false
    );
}
