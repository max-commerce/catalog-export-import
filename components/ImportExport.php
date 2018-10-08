<?php

namespace maxcom\catalog\exportimport\components;

use yii\base\InvalidConfigException;

class ImportExport extends \yii\base\Component {

    /** @var Запрос для экспорта */
    public $exportQuery;

    /** @var array Переопредляем атрибуты в человекопнятные названия */
    public $attributesToNames = [];

    /**
     * Сохраняем сюда продукты для обработки
     * @var array
     */
    private $_productsToProcess = [];

    /**
     * @var array Сохраняем сюда крышку для импорта
     */
    private $_importHead = [];

    /**
     * Входная точка импорта
     */
    public function import($array) {
        $this->_productsToProcess = $array;
        return $this->_importSave();
    }

    /**
     * Сохраняем продукты пришедшие с иморта
     */
    private function _importSave() {
        //TODO:
        $table = '';
        $table .= '<table class="table">';
        foreach ($this->_productsToProcess as $csvRow) {
            $table .= '<tr>';
                foreach ($csvRow as $rowValue) {
                    $table .= '<td>';
                        $table .= $rowValue;
                    $table .= '</td>';
                }
            $table .= '</tr>';
        }
        $table .= '</table>';
        return $table;
    }

    /**
     * @return array
     */
    private function getImportHead() {
        if($headRow = $this->_productsToProcess && empty($this->_importHead)) {
            $this->_importHead = array_values($headRow);
        }
        return $this->_importHead;
    }

    /**
     * Входная точка экспорта, возвращает массив данных о товарах
     * @return array
     */
    public function export() {
        $this->_prepareProductsToExport();
        $this->_setExportHead();
        return $this->_productsToProcess;
    }

    /**
     * Устанавливаем человекпонятные названия в начале массива экспорта
     * @return void
     */
    private function _setExportHead() {
        if(!empty($this->_productsToProcess)) {
            $keys = array_keys($this->_productsToProcess[0]);
            $head = [];
            foreach ($keys as $key) {
                if(isset($this->attributesToNames[$key])) {
                    $head[] = $this->attributesToNames[$key];
                } else {
                    $head[] = $key;
                }
            }
            array_unshift($this->_productsToProcess, $head);
        }
    }

    /**
     * Подготавливаем наши продукты к экспорту (заносим в асоциативный массив)
     * @return void
     */
    private function _prepareProductsToExport() {
        foreach ($this->_queryProducts()->asArray()->batch(300) as $batch) {
            foreach($batch as $partResult) {
                $this->_productsToProcess[] = $partResult;
            }
        }
    }

    /**
     * @return \yii\db\ActiveQuery
     * @throws InvalidConfigException
     * Строим запрос для получения продуктов
     */
    private function _queryProducts() {
        if($this->exportQuery) {
            return $this->exportQuery;
        } else {
            throw new InvalidConfigException('Пожалуйста задайте запрос для экспорта');
        }
    }
}