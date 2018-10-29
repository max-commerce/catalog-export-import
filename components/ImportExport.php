<?php

namespace maxcom\catalog\exportimport\components;

use maxcom\catalog\exportimport\models\Import;
use Mpdf\Tag\P;
use yii\base\ErrorException;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;

class ImportExport extends \yii\base\Component {
    /** @var Сюда записываем все о импорте  */
    public $importConfig;
    public $customAttributesMechs;
    public $exportFileColumnsToClassAttributes;

    private $_importMeta;
    private $_importBaseClass;

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
    public function import($array)
    {
        unset($array[array_keys($array)[0]]);
        $this->_productsToProcess = $array;
        return $this->_importSave();
    }

    private function _prepareFileMeta()
    {
        if(isset($this->importConfig['fileColumnsToClassAttributes'])) { //Проверим определены ли поля в конфиге
            $this->_importBaseClass = $this->importConfig['baseModelClass'];
            $this->_importMeta = $this->importConfig['fileColumnsToClassAttributes'];
        } else {
            throw new InvalidConfigException('Please set fileColumnsToClassAttributes property in your config');
        }
    }


    private function _saveRelation(ActiveRecord $model, $attribute, $propValue = null) :? ActiveRecord
    {
        if(gettype($attribute) == 'string' && strpos($attribute,'.')) { //Значит идет обращене к атрибуту релейшена
                //ПОлучаем метаданные о связи
            $relationPath = explode('.', $attribute);
            $relProp = $relationPath[0];
            $relAttr = $relationPath[1];
            $relMethod = 'get' . lcfirst($relProp);
            $relQuery = $model->$relMethod();
            $relModel = $relQuery->modelClass;
            $relLink = $relQuery->link;

            if($rel = $relModel::findOne([$relAttr => $propValue])) {//Ищем модель, если она существует привязываем ее к модели и сохраняем
                foreach ($relLink as $relKey => $modelKey) {
                    $model->{$modelKey} = $rel->{$relKey};
                }
                $model->save();
                return $rel;
            } else {
                $rel = new $relModel([
                    $relAttr => $propValue,
                ]);
                $rel->save();
                foreach ($relLink as $relKey => $modelKey) {
                    $model->{$modelKey} = $rel->{$relKey};
                }
                $model->save();
                return $rel;
            }
       }

        return null;
    }

    private function _processCustomAttributes($fileRow, $model) {
        foreach ($fileRow as $key => $value) {
            $attribute = $this->_importMeta[array_keys($this->_importMeta)[$key]];
            if($attribute instanceof \Closure) {
                $processMethod = $attribute;
                $processMethod($model, $value);
            }
        }
    }

    private function _saveRelationsData($fileRow, $model) {
        foreach ($fileRow as $key => $value) {
            $attribute = $this->_importMeta[array_keys($this->_importMeta)[$key]];
            $this->_saveRelation($model, $attribute, $value);
        }
    }



    /**
     * @inheritdoc
     * Для каждой строки из файла мы создаем по одной модели указаной в конфиге
     * Для каждой такой модели мы подсталяем атрибуты указаные в конфиге для каждой колонки
     */
    private function _importSave()
    {
        $this->_prepareFileMeta();
        foreach ($this->_productsToProcess as $key => $csvRow) { //Каждая стрка из файла
            /** @var  $model */
            $model = $this->_findModelByRow($csvRow);
            if(!$model->isNewRecord) { //Нашли основную модель
                $data = $this->_setAttributesByRow($csvRow, $model);
                $model->load([$model->formName() => $data]);
                $model->save();
                $this->_saveRelationsData($csvRow, $model);
                $this->_processCustomAttributes($csvRow, $model);
            } else { //Не нашли основную модель
                //Подготовим данные для LOAD
                $data = $this->_setAttributesByRow($csvRow, $model);
                $model->load($data);
                $model->save();
                $this->_saveRelationsData($csvRow, $model);
                $this->_processCustomAttributes($csvRow, $model);
            }
        }
        return true;
    }


    /**
     * @param $fileRow
     * @param $model ActiveRecord
     * @return array
     */
    private function _saveRelations($fileRow, $model) {
        $savedAttributes = [];
        foreach ($this->_importMeta as $key => $attribute) {
            if(!empty($model->{$attribute}) && $model->{$attribute} instanceof ActiveRecord) {
                $relationModel =  $model->{$attribute};
            }
        }
    }

    /**
     * @param $fileRow
     * Устанавливаем атрибуты модели по строке файла
     */
    private function _setAttributesByRow($fileRow, $model)
    {
        $result = [];
        foreach ($fileRow as $key => $value) {
            $attribute = $this->_importMeta[array_keys($this->_importMeta)[$key]];
            if(gettype($attribute) == 'string') {
                $result[$model->formName()][$attribute] = mb_convert_encoding($value, "utf-8", "windows-1251");
            }
        }
        return $result;
    }

    /**
     * @inheritdoc
     * Ищем данныые в базе по строке
     * @param $modelClass
     * @param $attribute
     * @param $fileRow
     */
    private function _findModelByRow($fileRow)
    {
        foreach ($fileRow as $key => $value) {
            $attribute = $this->_importMeta[array_keys($this->_importMeta)[$key]];
            if(empty($this->importConfig['baseModelSearchAttribute']) && $this->_getPrimaryByClassName($this->_importBaseClass) == $attribute) {
                $model = $this->_importBaseClass::findOne($value);
                if($model) {
                    return $model;
                } else {
                    $model = new $this->_importBaseClass;
                }
            } elseif(!empty($this->importConfig['baseModelSearchAttribute']) && $attribute == $this->importConfig['baseModelSearchAttribute']) {
                $model = $this->_importBaseClass::findOne([$attribute => $value]);
                if($model) {
                    return $model;
                } else {
                    $model = new $this->_importBaseClass;
                }
            }
        }
        if(empty($model)) throw new ErrorException('Can"t read this format');
        return $model;
    }

    private function _getPrimaryByClassName($modelClass)
    {
        $primaryKeyAttribute = false;
        //Получаем данные о первичном ключе
        $primaryKey = $modelClass::primaryKey();
        if(isset($primaryKey[0])) {
            $primaryKeyAttribute = $primaryKey[0];
        }
        return $primaryKeyAttribute;
    }

    /**
     * @return array
     */
    private function getImportHead()
    {
        if($headRow = $this->_productsToProcess && empty($this->_importHead)) {
            $this->_importHead = array_values($headRow);
        }
        return $this->_importHead;
    }

    /**
     * Входная точка экспорта, возвращает массив данных о товарах
     * @return array
     */
    public function export()
    {
        $this->_prepareProductsToExport();
        $this->_setExportHead();
        return $this->_productsToProcess;
    }

    /**
     * Устанавливаем человекпонятные названия в начале массива экспорта
     * @return void
     */
    private function _setExportHead()
    {
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
    private function _prepareProductsToExport()
    {
        foreach ($this->_queryProducts()->batch(300) as $batch) {
            foreach($batch as $partResultModel) {
                $this->_productsToProcess[] = $this->_processModelToExport($partResultModel);
            }
        }

    }

    private function _processModelToExport(ActiveRecord $model) {
        $columns = $this->_getExportFileColumns();
        $result = [];
        foreach ($columns as $column) {
            $result[] = $this->_processExportColumn($column, $model);
        }
        return $result;
    }

    private function _processExportColumn($column, ActiveRecord $model) {
        if($column instanceof \Closure) {
            return $this->_processExportClosureColumn($column, $model);
        } elseif(strpos($column,'.')) {
            return $this->_processExportRelationColumn($column, $model);
        } else {
            return $model->{$column};
        }
    }

    private function _processExportRelationColumn(string $attribute, ActiveRecord $model) :? string {
        $relationPath = explode('.', $attribute);
        $relProp = $relationPath[0];
        $relAttr = $relationPath[1];
        $relMethod = 'get' . lcfirst($relProp);
        $relQuery = $model->$relMethod();
        $relModel = $relQuery->modelClass;
        $relLink = $relQuery->link;
        if($rel = $model->{$relProp}) {
            return $rel->{$relAttr};
        } else {
            return null;
        }
    }
    private function _processExportClosureColumn(\Closure $closure, ActiveRecord $model) :? string {
        return $closure($model);
    }

    private  function _getExportFileColumns() {
        $resultRow = [];
        $columns = \Yii::$app->importExport->exportFileColumnsToClassAttributes;
        return $columns;
    }


    /**
     * @return \yii\db\ActiveQuery
     * @throws InvalidConfigException
     * Строим запрос для получения продуктов
     */
    private function _queryProducts()
    {
        if($this->exportQuery) {
            return $this->exportQuery;
        } else {
            throw new InvalidConfigException('Пожалуйста задайте запрос для экспорта');
        }
    }
}