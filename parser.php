<?php

/**
 * Parser
 *
 */
class Parser {

    private const PROP_OFFICE = 'OFFICE';               // 1    Комбинат/Офис:
    private const PROP_LOCATION = 'LOCATION';           // 2    Местоположение:
    private const PROP_NAME = 'NAME';                   // 3    Название вакансии
    private const PROP_REQUIRE = 'REQUIRE';             // 4
    private const PROP_DUTY = 'DUTY';                   // 5
    private const PROP_CONDITIONS = 'CONDITIONS';       // 6
    private const PROP_SALARY_VALUE = 'SALARY_VALUE';   // 7    Заработная плата (значение):
    private const PROP_TYPE = 'TYPE';                   // 8    Тип вакансии:
    private const PROP_ACTIVITY = 'ACTIVITY';           // 9    Тип занятости:
    private const PROP_SCHEDULE = 'SCHEDULE';           // 10   График работы:
    private const PROP_FIELD = 'FIELD';                 // 11   Сфера деятельности:
    private const PROP_EMAIL = 'EMAIL';                 // 12   Электронная почта (e-mail):
    private const PROP_DATE = 'DATE';
    private const PROP_SALARY_TYPE = 'SALARY_TYPE';     // = от до Договорная - получаем из SALARY_VALUE
    private const MAX_COUNT_CHARS = 255;                // максимальное кол-во символов

    private CIBlockElement $el;
    private $rsElement;
    private $rsProp;
    private $rsElements;
    private array $arProps = [];
    private array $PROP = [];
    private int $row = 1;

    function __construct(
        private int $userId,
        private int $iblockId,
        private string $dataFile
    ) {

        \Bitrix\Main\Loader::includeModule('iblock');

        $this->el = new CIBlockElement();

        // получает все элемены инфо блока
        $this->rsElement = CIBlockElement::getList([], ['IBLOCK_ID' => $this->iblockId],
                false, false, ['ID', 'NAME']);

        // получает варианты значений свойств типа "список".
        $this->rsProp = CIBlockPropertyEnum::GetList(
                ["SORT" => "ASC", "VALUE" => "ASC"],
                ['IBLOCK_ID' => $this->iblockId]
        );

        $this->rsElements = CIBlockElement::GetList([], ['IBLOCK_ID' => $this->iblockId],
                false, false, ['ID']);
    }

    /**
     * @inheritdoc
     */
    private function getPropsOffice() {
        while ($ob = $this->rsElement->GetNextElement()) {

            $arFields = $ob->GetFields();
            $key = strtolower(str_replace(['»', '«', '(', ')'], '', $arFields['NAME']));
            $arKey = explode(' ', $key);
            $key = '';
            foreach ($arKey as $part) {
                if (strlen($part) > 2) {
                    $key .= trim($part) . ' ';
                }
            }

            $this->arProps[self::PROP_OFFICE][trim($key)] = $arFields['ID'];
        }
    }

    /**
     * @inheritdoc
     */
    private function getPropsProperty() {
        while ($arProp = $this->rsProp->Fetch()) {
            $key = trim($arProp['VALUE']);
            $this->arProps[$arProp['PROPERTY_CODE']][$key] = $arProp['ID'];
        }
    }

    /**
     * @inheritdoc
     */
    private function deleteElements() {
        while ($element = $this->rsElements->GetNext()) {
            CIBlockElement::Delete($element['ID']);
        }
    }

    /**
     * @inheritdoc
     */
    private function fillPROP($data) {

        $this->PROP[self::PROP_ACTIVITY] = $data[9];
        $this->PROP[self::PROP_FIELD] = $data[11];
        $this->PROP[self::PROP_OFFICE] = $data[1];
        $this->PROP[self::PROP_LOCATION] = $data[2];
        $this->PROP[self::PROP_REQUIRE] = $data[4];
        $this->PROP[self::PROP_DUTY] = $data[5];
        $this->PROP[self::PROP_CONDITIONS] = $data[6];
        $this->PROP[self::PROP_EMAIL] = $data[12];
        $this->PROP[self::PROP_DATE] = date('d.m.Y');
        $this->PROP[self::PROP_TYPE] = $data[8];
        $this->PROP[self::PROP_SALARY_TYPE] = '';
        $this->PROP[self::PROP_SALARY_VALUE] = $data[7];
        $this->PROP[self::PROP_SCHEDULE] = $data[10];
    }

    /**
     * @inheritdoc
     */
    private function checkPropertyList($key, &$value) {

        $property = CIBlockProperty::GetByID($key, $this->iblockId)->GetNext();

        $value = explode('•', $value);
        array_splice($value, 0, 1);

        foreach ($value as &$str) {
            $str = trim($str, " \n\r\t\v\x00.;");

            // обрезает строку до 255 символов для запист в базу (varchar(255))
            if (mb_strlen($str) > self::MAX_COUNT_CHARS) {
                $str = mb_substr($str, 0, self::MAX_COUNT_CHARS);
            }

            // если значение свойства есть, получаем Id
            // если нету, добавляем значение и получаем Id
            $str = $this->getPropertyEnumId($str, $property['ID']);
        }
    }

    /**
     * @inheritdoc
     */
    private function getPropertyEnumId($str, $propertyId): int {
        $enum = CIBlockPropertyEnum::GetList(
                [], ['VALUE' => $str])->GetNext();

        if (!$enum) {
            $newEnum = new CIBlockPropertyEnum;

            return $newEnum->Add([
                    'PROPERTY_ID' => $propertyId,
                    'VALUE' => $str
            ]);
        } else {
            return $enum['ID'];
        }
    }

    /**
     * @inheritdoc
     */
    private function checkProperty($key, $location, &$value) {

        $arSimilar = [];

        foreach ($this->arProps[$key] as $propKey => $propVal) {
            if ($key == self::PROP_OFFICE) {
                $value = strtolower($value);
                if ($value == 'центральный офис') {
                    $value .= 'свеза ' . $location;
                } elseif ($value == 'лесозаготовка') {
                    $value = 'свеза ресурс ' . $value;
                } elseif ($value == 'свеза тюмень') {
                    $value = 'свеза тюмени';
                }
                $arSimilar[similar_text($value, $propKey)] = $propVal;
            }
            if (stripos($propKey, $value) !== false) {
                $value = $propVal;
                break;
            }

            if (similar_text($propKey, $value) > 50) {
                $value = $propVal;
            }
        }
        if ($key == self::PROP_OFFICE && !is_numeric($value)) {
            ksort($arSimilar);
            $value = array_pop($arSimilar);
        }
    }

    /**
     * @inheritdoc
     */
    private function fixProp($location) {
        foreach ($this->PROP as $key => &$value) {

            $value = str_replace('\n', '', trim($value));

            if (stripos($value, '•') !== false) {

                $this->checkPropertyList($key, $value);
            } elseif ($this->arProps[$key]) {

                $this->checkProperty($key, $location, $value);
            }
        }
    }

    /**
     * @inheritdoc
     */
    private function fixPropSalary() {

        if ($this->PROP[self::PROP_SALARY_VALUE] == '-') {

            $this->PROP[self::PROP_SALARY_VALUE] = '';
        } elseif ($this->PROP[self::PROP_SALARY_VALUE] == 'по договоренности') {

            $this->PROP[self::PROP_SALARY_VALUE] = '';
            $this->PROP[self::PROP_SALARY_TYPE] = $this->arProps[self::PROP_SALARY_TYPE]['договорная'];
        } else {

            $arSalary = explode(' ', $this->PROP[self::PROP_SALARY_VALUE]);

            if ($arSalary[0] === 'от' || $arSalary[0] === 'до') {

                $this->PROP[self::PROP_SALARY_TYPE] = $this->arProps[
                    self::PROP_SALARY_TYPE][$arSalary[0]
                ];

                array_splice($arSalary, 0, 1);

                $this->PROP[self::PROP_SALARY_VALUE] = implode(' ', $arSalary);
            } else {
                $this->PROP[self::PROP_SALARY_TYPE] = $this->arProps[self::PROP_SALARY_TYPE]['='];
            }
        }
    }

    /**
     * @inheritdoc
     */
    private function addElement($name, $isActive): string {
        $arLoadProductArray = [
            "MODIFIED_BY" => $this->userId,
            "IBLOCK_SECTION_ID" => false,
            "IBLOCK_ID" => $this->iblockId,
            "PROPERTY_VALUES" => $this->PROP,
            "NAME" => $name,
            "ACTIVE" => $isActive ? 'Y' : 'N',
        ];

        return ($PRODUCT_ID = $this->el->Add($arLoadProductArray)) ?
            "Добавлен элемент с ID : " . $PRODUCT_ID . "<br>" :
            "Error: " . $this->el->LAST_ERROR . "<br>";
    }

    /**
     * @inheritdoc
     */
    public function start() {

        $this->getPropsOffice();
        $this->getPropsProperty();

        if (($handle = fopen($this->dataFile, "r")) !== false) {

            $this->deleteElements();

            while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                if ($this->row == 1) {
                    $this->row++;
                    continue;
                }
                $this->row++;

                $this->fillPROP($data);

                $this->fixProp($data[2]);

                $this->fixPropSalary();

                echo $this->addElement($data[3], end($data));
            }

            fclose($handle);
        }
    }
}
