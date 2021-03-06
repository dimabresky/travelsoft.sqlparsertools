<?php

namespace travelsoft\sqlimporttools\export;

use travelsoft\sqlimporttools\Config;
use travelsoft\sqlimporttools\Tools;
use travelsoft\sqlimporttools\Files;

/**
 * Класс экспорта отелей
 *
 * @author dimabresky
 */
class Hotels extends Exporter {

    /**
     * @var string
     */
    public $short_export_name = "hotels";

    /**
     * @return boolean
     */
    public function startExport() {
        
        return self::_startExport("hotels", $this, function ($context) {

                    $existsIblockHotels = $context->_getIblockHotelsId();
                    $existsIblockCountries = $context->_getIblockCountriesId();
                    $existsIblockCities = $context->_getIblockCitiesId();

                    $files = new Files;

                    foreach ($context->_db_rows as $arRow) {

                        $arProperties = [
                            "DESC" => ["VALUE" => ["TYPE" => "HTML", "TEXT" => str_replace("\\n", "<br>", $arRow["description"])]],
                            "HOTELKEY" => $arRow["mthotelId"],
                            "TOWN" => in_array($arRow["cityId"], $existsIblockCities) ? $arRow["cityId"] : 0,
                            "COUNTRY" => in_array($arRow["countryId"], $existsIblockCountries) ? $arRow["countryId"] : 0,
                            "STARS" => $arRow["starsCount"],
                            "ADDRESS" => $arRow["hotelAddress"],
                            "MAP" => implode(",", [$arRow["hotelLatitude"], $arRow["hotelLongitude"]]),
                            "CHECKIN_FROM" => $arRow["checkinFrom"],
                            "CHECKIN_UNTIL" => $arRow["checkinUntil"],
                            "CHECKOUT_FROM" => $arRow["checkoutFrom"],
                            "CHECKOUT_UNTIL" => $arRow["checkoutUntil"],
                            "POLICY" => $context->_getPoliciesByXML_ID(Tools::extractStringLikeArray($arRow["policyId"], true)),
                            "SERVICES" => $context->_getFacilitiesByXML_ID(Tools::extractStringLikeArray($arRow["facilityId"], true)),
                            "XML_URL" => $arRow["hotelUrl"]
                        ];
                        
                        $arImages2Save = [];
                        foreach (Tools::extractStringLikeArray($arRow["hotelImagesPath"], true) as $k => $rel_path) {
                            $arImages2Save["n$k"] = ["VALUE" => $files->getFileUploadArray(Config::RELATIVE_MODULE_UPLOAD_DIR . "/" . $rel_path)];
                        }

                        if (isset($existsIblockHotels[$arRow["hotelId"]])) {

                            // try update
                            $is_updated = $context->_iblock_element_object->Update($existsIblockHotels[$arRow["hotelId"]], [
                                "NAME" => $arRow["hotelName"],
                                "XML_ID" => $arRow["hotelId"],
                                "CODE" => Tools::translit($arRow["hotelName"]) . "_" . $arRow["hotelId"]
                            ]);

                            if ($is_updated) {
                                
                                foreach ($arProperties as $code => $value) {
                                    
                                    $context->_iblock_element_object->SetPropertyValueCode($existsIblockHotels[$arRow["hotelId"]], $code, $value);
                                }

                                // удаление фото
                                $arPicture = $context->_iblock_element_object->GetProperty(Config::HOTELS_IBLOCK_ID, $existsIblockHotels[$arRow["hotelId"]], 'ID', 'DESC', array('CODE' => "PICTURES"))->Fetch();

                                $context->_iblock_element_object->SetPropertyValuesEx($existsIblockHotels[$arRow["hotelId"]], Config::HOTELS_IBLOCK_ID, array($arPicture["ID"] => array("VALUE" => ['del' => 'Y'])));

                            } else {

                                $context->errors[] = Tools::prepare2Log($context->_iblock_element_object->LAST_ERROR) . "[try update " . $arRow["hotelName"] . "]";
                            }

                            $ID = $existsIblockHotels[$arRow["hotelId"]];
                        } else {

                            // try add
                            $ID = $context->_iblock_element_object->Add([
                                "IBLOCK_ID" => Config::HOTELS_IBLOCK_ID,
                                "ACTIVE" => "Y",
                                "NAME" => $arRow["hotelName"],
                                "XML_ID" => $arRow["hotelId"],
                                "CODE" => Tools::translit($arRow["hotelName"]) . "_" . $arRow["hotelId"],
                                "PROPERTY_VALUES" => $arProperties
                            ]);

                            if ($ID > 0) {
                                $existsIblockHotels[$arRow["hotelId"]] = $ID;
                            } else {
                                $context->errors[] = Tools::prepare2Log($context->_iblock_element_object->LAST_ERROR) . "[try add " . $arRow["hotelName"] . "]";
                            }
                        }

                        if ($ID > 0) {
                            foreach ($arImages2Save as $arImage2Save) {
                                $context->_iblock_element_object->SetPropertyValueCode($ID, "PICTURES", $arImage2Save);
                            }
                        }
                    }
                });
    }

    /**
     * @staticvar array $arPolicies
     * @param array $XML_IDs
     * @return array
     */
    protected function _getPoliciesByXML_ID(array $XML_IDs) {
        
        return (array)\array_values(\array_map(function ($policy_id) {
            return $policy_id;
        }, $this->_getIblockPoliciesId($XML_IDs)));
        
    }

    /**
     * @staticvar array $arFacilities
     * @param array $XML_IDs
     * @return array
     */
    protected function _getFacilitiesByXML_ID(array $XML_IDs) {
        
        return (array)\array_values(\array_map(function ($facility_id) {
            return $facility_id;
        }, $this->_getIblockFacilitiesId($XML_IDs)));
        
    }

}
