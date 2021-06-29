<?php

namespace OSC\GSE\ASU\Models;

use QUAINT\DEV\HLEntities\Citizenship;
use  QUAINT\DEV\HLEntities\DocTypes;

class DocType
{
    private $validatorRX = false;
    private $isNeededCitizenship = true;

    private $doc_code;
    private $citizenship = null;

    const THROW_UNKNOWN_DOCTYPE = 1010001;

    const RUSSIAN_PASSPORT = "ПН";
    const BIRTH_CERTIFICATE = "СР";
    const RUSSIAN_FOREIGN_PASSPORT = "ЗП";
    const MILITARY_CARD = "ВБ";
    const SAILOR_PASSPORT = "ПМ";
    const USSR_PASSPORT = "ПС";
    const FOREIGN_PASSPORT = "ЗЗ";
    const MULTIFUNCTIONAL_CARD = "МК";

    /**
     * DocType constructor.
     *
     * @param $doc_code
     * @throws \Exception
     */
    public function __construct($doc_code)
    {
        $this->doc_code = $doc_code;

        $docTypesHL = DocTypes::getList();
        $arDocTypes = $docTypesHL->fetchAll();
        $arDocTypes = array_combine(array_column($arDocTypes, "UF_DT_CODE"), $arDocTypes);

        if (!isset($arDocTypes[$doc_code])) {
            throw new \Exception("Неизвестный тип документа ({$doc_code})", $this::THROW_UNKNOWN_DOCTYPE);
        }

        if ($arDocTypes[$doc_code]["UF_DT_REGEX"]) {
            $this->validatorRX = "/{$arDocTypes[$doc_code]["UF_DT_REGEX"]}/iu";
        }

        if ($arDocTypes[$doc_code]["UF_DT_FIXED_CTZNSHP"]) {
            $citizenship = Citizenship::getById($arDocTypes[$doc_code]["UF_DT_FIXED_CTZNSHP"])->fetch();

            if ($citizenship) {
                $this->citizenship = $citizenship["UF_CC_ALPHA3"];
            }
        }
    }

    public function validateDocNumber($num)
    {
        if($this->validatorRX !== false) {
            return preg_match($this->validatorRX, $num);
        }

        return true;
    }

    public function hasFixedCitizenship()
    {
        return !!$this->citizenship;
    }

    public function getCitizenship()
    {
        return $this->citizenship;
    }
}