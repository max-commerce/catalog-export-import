<?php

namespace maxcom\catalog\exportimport\actions;

use Keboola\Csv\CsvReader;
use League\Csv\CharsetConverter;
use League\Csv\Reader;
use maxcom\catalog\exportimport\components\ImportExport;
use maxcom\catalog\exportimport\models\Import;
use Symfony\Component\Translation\Loader\CsvFileLoader;
use Yii;
use yii\base\Action;
use yii\db\ActiveRecord;
use yii\helpers\Html;
use yii\web\Response;
use yii\web\UploadedFile;
use yiiunit\extensions\jui\SelectableTest;

class ImportAction extends Action
{

    public function run()
    {
        if (Yii::$app->request->isPost) {
            return $this->do();
        } else {
            return $this->getFile();
        }
    }

    private function do()
    {
        set_time_limit(0);

        $model = new Import();
        $model->importFile = UploadedFile::getInstance($model, 'importFile');

        $reader = Reader::createFromPath($model->importFile->tempName,'r');
        CharsetConverter::addTo($reader,'windows-1251','utf-8');
        $reader->setDelimiter(';');
        $records = $reader->getRecords();
        foreach($records as $offset => $record) {
            $data[] = $record;
        }

        $porter = Yii::$app->importExport;
        $model->importFile = null;
        $model->load(Yii::$app->request->post());

        if($model->flushMainTable && isset(Yii::$app->importExport->importConfig['baseModelClass'])) {
            /** @var ActiveRecord $model */
            $model = Yii::$app->importExport->importConfig['baseModelClass'];
            $cmd = Yii::$app->db->createCommand('SET FOREIGN_KEY_CHECKS=0;  TRUNCATE ' . $model::tableName())->execute();
        }
        if ($content = $porter->import($data)) {
            Yii::$app->session->setFlash('success', 'Импорт прошел успешно');
            return $this->controller->renderContent($content);
        }

    }

    private function getFile()
    {
        $model = new Import();
        return $this->controller->render('@vendor/max-commerce/catalog-export-import/views/get-file', [
            'model' => $model,
        ]);
    }

    public function import() {
        $cfg = [
            'A' => [
                'class' => 'ShopProducts',
                'attribute' => 'name',
            ],
            'B' => [
                'class' => 'ShopProductsAttributes',
                'attribute' => 'value',
            ]
        ];
    }
}