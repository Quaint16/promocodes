<?php

namespace QUAINT\DEV;

use Bitrix\Main\Type\DateTime;
use QUAINT\DEV\Tables\PromocodesQueueTable;
use QUAINT\DEV\ASU\Models\DocType;
use QUAINT\DEV\ASU\Request;

/**
 * Class XlsPromocodes
 * @package  QUAINT\DEV
 *
 * @description Класс для управления очередью отправки промокодов
 */
class XlsPromocodes
{
    private $maxOperation = 1;
    private $limit = 3;

    public function __construct()
    {
        $this->count = $this->setCount();
    }


    public static function addRow($data)
    {
        $rowData = [
            "PROMOCODE" => $data["Card"],
            "JSON_DATA" => json_encode($data, JSON_UNESCAPED_UNICODE),
            "SUCCESS_EXEC" => 0,
            "TRIES_COUNT" => 0,
            "ERROR_MESSAGE" => null,
            "CREATION_DATE" => new DateTime(),
            "LAST_UPDATE" => new DateTime(),
        ];
        $res = PromocodesQueueTable::add($rowData);
        return $res;
    }

    public static function submitObject($js_data = null)
    {

        $objArray = json_decode($js_data);
        $activePromo = new ModifyCard($objArray);
        $response = array("Type" => "ModifyCard");
        $res = Request::getInstance()->submitObject($activePromo, $response);
        $_SESSION['PROMO']++;

        return $res;
    }

    public static function parseXLSX($url, $row, $column)
    {

        $exel = new QUAINT\DEV\Spreadsheet\OSCExcel($url);
        try {
            $res = $exel->getTable($row, $column);
           
            foreach ($res as $result) {


                $result["G"] = str_replace("/",".", $result["G"]);
                $birthday = explode(".", $result["G"]);
                $result["G"] = str_pad($birthday[1], 2, '0', STR_PAD_LEFT).".".str_pad($birthday[0], 2, '0', STR_PAD_LEFT).".".$birthday[2];
                $birthday = $result["G"];
        
                $date = explode("-", $result["J"]);
                $doc = explode(" ", $result["D"]);

                $params['ID'] = $result["B"];
                $params['Card'] = $result["I"];
                $params['TariffPlan'] = $result["L"];
                $params['DateStart'] = $date[0];
                $params['DateStop'] = $date[1];
                $params['ChangeStatus'] = '1';
                $params['DocType'] = $doc[0];
                $params['Doc'] = $doc[1] . $doc[2];
                $params['Name'] = str_replace(" ", "=", $result["C"]);
                $params['Birthday'] = $birthday;
                $params['Citizenship'] = $result["E"];
                $params['Sex'] = $result["F"];
                $params['Phone'] = $result["H"];

                $add = self::addRow($params);
            }

        } catch (Exception $e) {
            Utils::setLogPath("Promocode/getTable");
            Utils::log([$e->getMessage(), $e->getTraceAsString()]);
        }

        return $res;
    }

    public function getList($offset = 0)
    {
        $res = PromocodesQueueTable::getList([
            "order" => ["ID" => "asc"],
            "filter" => [
                "==LOCKED" => false,
                "==SUCCESS_EXEC" => false,
                "<TRIES_COUNT" => $this->maxOperation,
            ],
            "limit" => $this->limit,
            /* "offset" => $offset ?? $offset */
        ])->fetchAll();

        return $res;
    }

    private function setCount()
    {
        return PromocodesQueueTable::getCount([
            "==LOCKED" => false,
            "==SUCCESS_EXEC" => false,
            "<TRIES_COUNT" => $this->maxOperation,
        ]);
    }

    public function getCount()
    {
        return $this->count;
    }

    public function getLimit()
    {
        return $this->limit;
    }

    public function sendToASU($offset)
    {
       
        $res = $this->getList($offset);
        $items = [];

        if (!empty($res)) { 
            foreach ($res as $item) {
                $updateData = [
                    "LAST_UPDATE" => new DateTime(),
                    "TRIES_COUNT" => $item["TRIES_COUNT"] + 1
                ]; 
                $item["G"] = str_replace("/",".", $item["G"]);
                $birthday = explode(".", $item["G"]);
                $item["G"] = str_pad($birthday[1], 2, '0', STR_PAD_LEFT).".".str_pad($birthday[0], 2, '0', STR_PAD_LEFT).".".$birthday[2];
                $birthday = $item["G"];

                $jsonData = json_decode($item["JSON_DATA"], true);
                $items[$item["ID"]] = [
                    "code" => $item["PROMOCODE"],
                    "fio" => $jsonData["Name"],
                    "str" => "P04R103W".$item["PROMOCODE"]."D".$jsonData["DateStart"]."-".$jsonData["DateStop"]."T".$jsonData["TariffPlan"]."K001X<".$jsonData["DocType"].$jsonData["Doc"]."/".$jsonData["Name"]."/".$jsonData["Birthday"]."/".$jsonData["Citizenship"]."/".$jsonData["Sex"]."/+".$jsonData["Phone"].">",
                ];
                try {
                    $response = self::submitObject($item["JSON_DATA"]);
                    
                    if (is_array($response) && isset($response["ERROR"])) {
                        throw new \Exception($response["ERROR"]["MESSAGE"]);
                    }
                    $updateData["SUCCESS_EXEC"] = 1;
                    $items[$item["ID"]]["status"] = "success";

                } catch (\Exception $e) {
                    Utils::setLogPath("XlsParse/Error");
                    Utils::log([$e->getMessage(), $e->getTraceAsString()]);
                    $updateData["ERROR_MESSAGE"] = $e->getMessage();
                    $items[$item["ID"]]["status"] = "error";
                    $items[$item["ID"]]["error_message"] = $e->getMessage();
                }
                PromocodesQueueTable::update($item["ID"], $updateData);
            }
        }

        return ["status" => "success", "items" => $items];
    }
}
