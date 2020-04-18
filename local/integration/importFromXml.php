<?
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

use Bitrix\Highloadblock\HighloadBlockTable as HLBT;
CModule::IncludeModule('highloadblock');


class TablesImportFromXmlController
{
    private $config;
    public $products;
    public $catalogTablesId;
    public $offersTableId;
    public $sykno;
    public $vikraska;


    public function __construct($config, $xml)
    {
        if (file_exists($config)) {
            $this->config = include($config);
        } else {
            exit('Не удалось открыть файл концигурации.');
        }

        if (file_exists($xml)) {
            $this->products = simplexml_load_file($xml);
        } else {
            exit('Не удалось открыть файл xml.');
        }

        $this->catalogTablesId = $this->config['catalog_tables_id'];
        $this->offersTableId = $this->config['offers_tables_id'];
    }

    public function getSyknoAndVikraskaFromXml()
    {
        foreach ($this->products as $product) {
            foreach ($product->SYKNO->VARIANT as $sykno) {
                if (!in_array((string)$sykno, $this->sykno)) {
                    $this->sykno[(string)$sykno] = (string)$sykno['VALUE'];
                }
            }

            foreach ($product->VIKRASKA->VARIANT as $vikraska) {
                if (!in_array((string)$vikraska, $this->vikraska)) {
                    $this->vikraska[(string)$vikraska] = (string)$vikraska['VALUE'];
                }
            }
        }

        return $this;
    }

    public function getArrayFromBitrix($name)
    {
        $property_enums = CIBlockPropertyEnum::GetList(["DEF" => "DESC", "SORT" => "ASC"], ['IBLOCK_ID' => $this->catalogTablesId, 'CODE' => $name]);

        while ($enum_fields = $property_enums->GetNext()) {
            $this->sykno[$enum_fields["ID"]] = $enum_fields["VALUE"];
        }
    }

    public function createHighloadBlock($name)
    {
        $hlblock = HLBT::getList([
            'filter' => ['=NAME' => $name]
        ])->fetch();

        if($hlblock){
            return $this;
        }

        $highloadBlockData = [
            'NAME' => $name,
            'TABLE_NAME' => 'skillbox_table_' . strtolower($name)
        ];

        $result = HLBT::add($highloadBlockData);
        $highLoadBlockId = $result->getId();

        if (!$highLoadBlockId) {
            var_dump($result->getErrorMessages());
        }

        $UFObject = 'HLBLOCK_' . $highLoadBlockId;

        $arrSyknoFields = [
            'UF_XML_ID' => [
                'ENTITY_ID' => $UFObject,
                'FIELD_NAME' => 'UF_XML_ID',
                'USER_TYPE_ID' => 'string',
                'MANDATORY' => 'Y',
            ],
            'UF_NAME' => [
                'ENTITY_ID' => $UFObject,
                'FIELD_NAME' => 'UF_NAME',
                'USER_TYPE_ID' => 'string',
                'MANDATORY' => 'Y',
            ],
        ];

        $arrSavedFieldsRes = [];
        foreach ($arrSyknoFields as $arrSyknoField) {
            $obUserField = new CUserTypeEntity;
            $ID = $obUserField->Add($arrSyknoField);
            $arrSavedFieldsRes[] = $ID;
        }

        $hlblock = HLBT::getById($highLoadBlockId)->fetch();
        $entity = HLBT::compileEntity($hlblock);
        $entity_data_class = $entity->getDataClass();

        $name = strtolower($name);

        foreach ($this->$name as $key => $value) {
            $arElementFields = [
                'UF_NAME' => $value,
                'UF_XML_ID' => $key,
            ];
            $otvet = $entity_data_class::add($arElementFields);
            if (!$otvet->isSuccess()) {
                echo 'Ошибка: ' . implode(', ', $otvet->getErrors()) . "";
                continue;
            }
        }

        return $this;
    }

    public function addGoods()
    {
        $ciBlockElement = new CIBlockElement;

        foreach ($this->products as $product) {

            $syknoArr = [];
            $vikraskaArr = [];

            foreach ($product->SYKNO->VARIANT as $syknoColor) {
                $syknoArr[] = (string)$syknoColor;
            }

            foreach ($product->VIKRASKA->VARIANT as $vikraska) {
                $vikraskaArr[] = (string)$vikraska;
            }

            $rsItems = CIBlockElement::GetList([], ['IBLOCK_ID' => $this->catalogTablesId, '=NAME' => $product->NAME], false, false, ['ID']);
            if ($arItem = $rsItems->GetNext()) {
                continue;
            }

            $product_id = $ciBlockElement->Add([
                'IBLOCK_ID' => $this->catalogTablesId,
                'NAME' => $product->NAME,
                'ACTIVE' => 'Y',
                'PROPERTY_VALUES' => [
                    'OLDID' => $product->OLDID,
                    'DESCRIPTION' => $product->DESCRIPTION,
                    'SYKNO' => $syknoArr,
                    'VIKRASKA' => $vikraskaArr
                ]
            ]);

            if (!empty($ciBlockElement->LAST_ERROR)) {
                echo "Ошибка добавления товара: " . $ciBlockElement->LAST_ERROR;
                die();
            }

            foreach ($product->OFFERS->OFFER as $OFFER) {

                $arLoadProductArray = [
                    'IBLOCK_ID' => $this->offersTableId,
                    'NAME' => 'Стол ' . $OFFER->ART,
                    'ACTIVE' => 'Y',
                    'PROPERTY_VALUES' => [
                        'CML2_LINK' => $product_id,
                        'SIZE_FIELD' => $OFFER->SIZE_FIELD,
                        'GAME_TYPE' => $OFFER->GAME_TYPE,
                        'TABLE_MATERIAL' => $OFFER->TABLE_MATERIAL
                    ]
                ];

                $product_offer_id = $ciBlockElement->Add($arLoadProductArray);
                if (!empty($ciBlockElement->LAST_ERROR)) {
                    echo "Ошибка добавления торгового предложения: " . $ciBlockElement->LAST_ERROR;
                    die();
                }

                CCatalogProduct::Add([
                    'ID' => $product_offer_id,
                    'QUANTITY' => 50
                ]);

                CPrice::Add([
                    'CURRENCY' => 'RUB',
                    'PRICE' => $OFFER->PRICE,
                    'PRODUCT_ID' => $product_offer_id,
                ]);
            }
        }
    }
}

pre('start');

$config = __DIR__ . '/settings.php';
$xml = __DIR__ . '/data/data.xml';

$importXml = new TablesImportFromXmlController($config, $xml);

$importXml
    ->getSyknoAndVikraskaFromXml()
    ->createHighloadBlock('Sykno')
    ->createHighloadBlock('Vikraska')
    ->addGoods();

pre('done.');