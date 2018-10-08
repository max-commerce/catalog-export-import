<?php

namespace maxcom\catalog\exportimport\actions;

use maxcom\catalog\exportimport\components\ImportExport;

use maxcom\catalog\exportimport\models\Import;
use Yii;
use yii\base\Action;
use yii\helpers\Html;
use yii\web\Response;
use yii\web\UploadedFile;

class ImportAction extends Action {

    public function run() {
        if(Yii::$app->request->isPost) {
            return $this->do();
        } else {
            return $this->getFile();
        }
    }

    private function getFile() {
        $model = new Import();
        return $this->controller->render('@vendor/max-commerce/catalog-export-import/views/get-file', [
            'model' => $model,
        ]);
    }

    private function do() {
        $model = new Import();
        $model->importFile = UploadedFile::getInstance($model,'importFile');
        $fileHandle = fopen($model->importFile->tempName,'r');
        $data = [];
        while($line = fgets($fileHandle)) {
            $data[] = str_getcsv($line,';');
        }
        $porter = Yii::$app->importExport;
        if($content = $porter->import($data)) {
            Yii::$app->session->setFlash('success','Импорт прошел успешно');
            return $this->controller->renderContent($content);
        }

    }
}