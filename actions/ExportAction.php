<?php

namespace maxcom\catalog\exportimport\actions;

use maxcom\catalog\exportimport\components\ImportExport;
use Yii;
use yii\base\Action;
use yii\web\Response;

class ExportAction extends Action {

    public function run() {
        $porter = Yii::$app->importExport;
        $data = $porter->export();
        tempnam(sys_get_temp_dir(),'__');
        $file = tmpfile();
        foreach ($data as $dataString) {
            fputcsv($file, $dataString, ';');
        }
        $this->setHeaders();
        echo file_get_contents(stream_get_meta_data($file)['uri']);
        exit;
    }

    public function setHeaders() {
        Yii::$app->response->format = Response::FORMAT_RAW;
        header('Content-Encoding: UTF-8');
        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename=export-" . date('Y-m-d-H-i') . ".csv");
        header("Pragma: no-cache");
        header("Expires: 0");
        echo "\xEF\xBB\xBF"; // UTF-8 BOM
    }
}