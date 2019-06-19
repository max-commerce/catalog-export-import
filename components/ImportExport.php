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

    private function _getRelationMetaByAttribute($attribute, $model) {
        list($relProp, $relAttr) = explode('.', $attribute);
        $relMethod = 'get' . lcfirst($relProp); //getCategory
        $relQuery = $model->$relMethod(); // $model->getCategory()
        $relModel = $relQuery->modelClass; // $model->getCategory()->modelClass
        $relLink = $relQuery->link; // ['category_id', 'id']

        return [
            'method' => $relMethod,
            'query' => $relQuery,
            'model' => $relModel,
            'link' => $relLink
        ];
    }

    private function _getRelationMethodByAttribute($attribute, $model) {
        return $this->_getRelationMetaByAttribute($attribute, $model)['method'];
    }

    private function _getRelationQueryByAttribute($attribute, $model) {
        return $this->_getRelationMetaByAttribute($attribute, $model)['query'];
    }

    private function _getRelationModelByAttribute($attribute, $model) {
        return $this->_getRelationMetaByAttribute($attribute, $model)['model'];
    }

    private function _getRelationLinkByAttribute($attribute, $model) {
        return $this->_getRelationMetaByAttribute($attribute, $model)['link'];
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




    /**
     * @inheritdoc
     * Для каждой строки из файла мы создаем по одной модели указаной в конфиге
     * Для каждой такой модели мы подсталяем атрибуты указаные в конфиге для каждой колонки
     */
    private function _importSave()
    {
        $this->_prepareFileMeta();
        foreach ($this->_productsToProcess as $key => $csvRow) { //Каждая стрка из файла
            if(!empty($csvRow)) {
                /** @var  $model */
                $model = $this->_findModelByRow($csvRow);
                if(!$model->isNewRecord) { //Нашли основную модель
                    $data = $this->_setAttributesByRow($csvRow, $model);
                    $model->load($data);
                    $model->save();
                    $this->_processCustomAttributes($csvRow, $model);
                } else { //Не нашли основную модель
                    //Подготовим данные для LOAD
                    $data = $this->_setAttributesByRow($csvRow, $model);
                    $model->load($data);
                    $model->save();
                    $this->_processCustomAttributes($csvRow, $model);
                }
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
                $this->_processStringAttribute($result, $attribute, $value, $model);
            }
        }
        return $result;
    }

    private function _processStringAttribute(&$result, $attribute, $value, $model) {
        if(strpos($attribute, '.')) {
            $this->_processRelationRequiredAttribute($result, $attribute, $value, $model);
        } else {
            $result[$model->formName()][$attribute] = $value;
        }

    }

    private function _processRelationRequiredAttribute(&$result, $attribute, $value, $model) {
        list($relProp, $relAttr) = explode('.', $attribute);

        $link = $this->_getRelationLinkByAttribute($attribute, $model);

        $linkRelModelAttribute = array_keys($link)[0];
        $linkBaseModelAttribute = $link[$linkRelModelAttribute];

        $relationModel = $this->_getRelationModelByAttribute($attribute, $model);
        $relationModel = $relationModel::findOne([$relAttr => $value]);
        IF($relationModel) {
            $result[$model->formName()][$linkBaseModelAttribute] = $relationModel->{$linkRelModelAttribute};
        } else {
            \Yii::error('Найдена модель для связи ' . $this->_getRelationMethodByAttribute($attribute, $model));
        }

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
        list($relProp, $relAttr) = explode('.', $attribute);
        list($relMethod, $relQuery, $relModel, $relLink) = array_values($this->_getRelationMetaByAttribute($attribute, $model));

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