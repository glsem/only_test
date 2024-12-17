<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

if (!CModule::IncludeModule('iblock')) {
    return;
}
$arIBlockType = CIBlockParameters::GetIBlockTypes();
$arInfoBlocks = array();
$arFilterInfoBlocks = array('ACTIVE' => 'Y');
$arOrderInfoBlocks = array('SORT' => 'ASC');
if (!empty($arCurrentValues['IBLOCK_TYPE'])) {
    $arFilterInfoBlocks['TYPE'] = $arCurrentValues['IBLOCK_TYPE'];
}

$rsIBlock = CIBlock::GetList($arOrderInfoBlocks, $arFilterInfoBlocks);
while ($obIBlock = $rsIBlock->Fetch()) {
    $arInfoBlocks[$obIBlock['ID']] = '[' . $obIBlock['ID'] . '] ' . $obIBlock['NAME'];
}

$arComponentParameters = array(
    "GROUPS" => array(
        "TEST" => array(
            "NAME" => 'Тест'
        ),
    ),
    'PARAMETERS' => array(
        // выбор типа инфоблока
        'IBLOCK_TYPE' => array(
            'PARENT' => 'BASE',
            'NAME' => 'Выберите тип инфоблока',
            'TYPE' => 'LIST',
            'VALUES' => $arIBlockType,
            'REFRESH' => 'Y',
            'DEFAULT' => 'news',
            'MULTIPLE' => 'N',
        ),
        // выбор самого инфоблока
        'IBLOCK_ID' => array(
            'PARENT' => 'BASE',
            'NAME' => 'Выберите родительский инфоблок',
            'TYPE' => 'LIST',
            'VALUES' => $arInfoBlocks,
            'REFRESH' => 'Y',
            "DEFAULT" => '',
            "ADDITIONAL_VALUES" => "Y",
        ),
    ),
);