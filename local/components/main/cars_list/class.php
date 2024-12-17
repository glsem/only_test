<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\SystemException;
use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockTable;

class AvailableCars extends CBitrixComponent
{
    public function executeComponent()
    {
        try {
            $this->checkModules();
            $this->getResult();
        } catch (SystemException $e) {
            ShowError($e->getMessage());
        }
    }

    public function onIncludeComponentLang()
    {
        Loc::loadMessages(__FILE__);
    }

    protected function checkModules()
    {
        if (!Loader::includeModule('iblock'))
            throw new SystemException(Loc::getMessage('IBLOCK_MODULE_NOT_INSTALLED'));
        if (!Loader::includeModule('highloadblock'))
            throw new SystemException(Loc::getMessage('HIGHLOAD_MODULE_NOT_INSTALLED'));
    }

    public function onPrepareComponentParams($arParams)
    {
        $request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
        $arParams['START_TIME'] = $request->get("startTime");
        $arParams['END_TIME'] = $request->get("endTime");
        if (empty($arParams['START_TIME']) || empty($arParams['END_TIME']) || !$this->isTimestamp($arParams['START_TIME']) || !$this->isTimestamp($arParams['END_TIME'])) {
            ShowMessage(Loc::getMessage('TIME_NOT_TRANSMITTED'));
            die();
        }
        $arParams['START_TIME'] = date('Y-m-d H:i:s', $arParams['START_TIME']);
        $arParams['END_TIME'] = date('Y-m-d H:i:s', $arParams['END_TIME']);
        if($arParams['START_TIME'] < date('Y-m-d H:i:s') || $arParams['END_TIME'] < date('Y-m-d H:i:s')) {
            ShowMessage(Loc::getMessage('TIME_NOT_TRANSMITTED'));
            die();
        }

        global $USER;
        if (!$USER->isAuthorized()) {
            ShowMessage(Loc::getMessage('TIME_NOT_TRANSMITTED'));
            die();
        }
        $arParams['USER_GROUPS'] = $USER->GetUserGroup($USER->GetId());

        return $arParams;
    }

    protected function isTimestamp($string)
    {
        try {
            new DateTime('@' . $string);
        } catch(Exception $e) {
            return false;
        }
        return true;
    }

    protected function getResult()
    {
        //Узнаем доступные пользователю категории
        $arHLBlockPositions = Bitrix\Highloadblock\HighloadBlockTable::getById(HL_POSITIONS)->fetch();
        $obEntityPositions = Bitrix\Highloadblock\HighloadBlockTable::compileEntity($arHLBlockPositions);
        $strEntityDataClass = $obEntityPositions->getDataClass();
        $positionData = $strEntityDataClass::getList(array(
            'select' => array('ID', 'UF_GROUP_ID', 'UF_CATEGORIES'),
            'filter' => array('UF_GROUP_ID' => $this->arParams['USER_GROUPS'], '!UF_CATEGORIES' => false)
        ));
        if ($arPosition = $positionData->Fetch()) {
            //получаем список полей категорий пользователя
            $arHLBlockCategories = Bitrix\Highloadblock\HighloadBlockTable::getById(HL_CATEGORIES)->fetch();
            $obEntityCategories = Bitrix\Highloadblock\HighloadBlockTable::compileEntity($arHLBlockCategories);
            $strEntityDataClass = $obEntityCategories->getDataClass();
            $categoriesData = $strEntityDataClass::getList(array(
                'select' => array('ID', 'UF_NAME', 'UF_XML_ID'),
                'filter' => array('ID' => $arPosition['UF_CATEGORIES'])
            ));
            $userCategories = array();
            $userCategoriesXmlId = array();
            while($category = $categoriesData->Fetch()) {
                $userCategories[$category['UF_XML_ID']] = $category;
                $userCategoriesXmlId[$category['UF_XML_ID']] = $category['UF_XML_ID'];
            }

            //получаем список автомобилей недоступных к бронированию
            $unavailibleCarsFilter = array(
                'IBLOCK_ID' => IB_TRIP_REQUESTS,
                'ACTIVE' => 'Y',
                '>=PROPERTY_END_TIME' => $this->arParams['START_TIME'],
                array(
                    'LOGIC' => 'OR',
                    array(
                        '<=PROPERTY_START_TIME' => $this->arParams['END_TIME'],
                        '>=PROPERTY_END_TIME' => $this->arParams['START_TIME'],
                    ),
                ),
            );
            $unavailibleCarsSelect = array('ID', 'IBLOCK_ID', 'NAME', 'PROPERTY_CAR');
            $unavailibleCarsData = CIBlockElement::GetList(array(), $unavailibleCarsFilter, false, false, $unavailibleCarsSelect);
            $unavailibleCarsList = array();
            while ($arCar = $unavailibleCarsData->Fetch()) {
                $unavailibleCarsList[] = $arCar['PROPERTY_CAR_VALUE'];
            }

            //получаем список доступных автомобилей
            $availableCarsFilter = array(
                'IBLOCK_ID' => IB_CAR,
                'ACTIVE' => 'Y',
                '!ID' => $unavailibleCarsList,
                'PROPERTY_CATEGORY' => $userCategoriesXmlId,
            );
            $availableCarsSelect = array('ID', 'IBLOCK_ID', 'NAME', 'PROPERTY_CATEGORY', 'PROPERTY_DRIVER', 'PROPERTY_MODEL');
            $availableCarsData = CIBlockElement::GetList(array(), $availableCarsFilter, false, false, $availableCarsSelect);
            $availableCarsList = array();
            while ($arCar = $availableCarsData->Fetch()) {
                $arCar['CATEGORY'] = $userCategories[$arCar['PROPERTY_CATEGORY_VALUE']]['UF_NAME'];
                $driverFilter = array('IBLOCK_ID' => IB_DRIVERS, 'ID' => $arCar['PROPERTY_DRIVER']);
                $driverSelect = array('ID', 'NAME', 'PROPERTY_NAME', 'PROPERTY_FNAME');
                $driverData = CIBlockElement::GetList(array(), $driverFilter, false, false, $driverSelect);
                if($driver = $driverData->Fetch()) {
                    $arCar['DRIVER'] = $driver;
                }
                $availableCarsList[] = $arCar;
            }

            $this->arResult['ITEMS'] = $availableCarsList;
            $this->IncludeComponentTemplate();
        } else {
            ShowMessage(Loc::getMessage('NO_PERMISSIONS'));
            die();
        }
    }
}
