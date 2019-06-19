<?php

namespace maxcom\catalog\exportimport\actions;

use maxcom\catalog\exportimport\components\ImportExport;
use voku\helper\UTF8;
use Yii;
use yii\base\Action;
use yii\web\Response;

class ExportAction extends Action {

    public function run() {
        $porter = Yii::$app->importExport;
        $data = $porter->export();
        tempnam(sys_get_temp_dir(),'__');
        $file = tmpfile();
        $mode = 'xls';
        if($mode == 'csv') {
            $this->putCsv($data, $file);
        } else {
            $this->putXls($data, $file);
        }
    }

    private function  putCsv($data, $file) {
        foreach ($data as $dataString) {
            fputcsv($file, $dataString, ';');
        }

        $this->setHeaders();
        echo file_get_contents(stream_get_meta_data($file)['uri']);
        exit;
    }

    private function  putXls($data, $file) {
        // Создаем объект класса PHPExcel
        $xls = new PHPExcel();
        // Устанавливаем индекс активного листа
        $xls->setActiveSheetIndex(0);
        // Получаем активный лист
        $sheet = $xls->getActiveSheet();
        // Подписываем лист
        $title =  Yii::$app->name . ' от ' . date('Y-m-d H-i-s');
        $sheet->setTitle($title);

        for($row = 0; $row < count($data); $row++) {
            $cells = $data[$row];
            for($cell = 0; $cell < count($cells); $cell++) {
                $sheet->setCellValueByColumnAndRow($cell, $row+1, $data[$row][$cell]);
            }
        }

        // Выводим HTTP-заголовки
        header ( "Expires: Mon, 1 Apr 1974 05:00:00 GMT" );
        header ( "Last-Modified: " . gmdate("D,d M YH:i:s") . " GMT" );
        header ( "Cache-Control: no-cache, must-revalidate" );
        header ( "Pragma: no-cache" );
        header ( "Content-type: application/vnd.ms-excel" );
        header ( "Content-Disposition: attachment; filename=" . $title . '.xls' );

        // Выводим содержимое файла
        $objWriter = new PHPExcel_Writer_Excel5($xls);
        $objWriter->save('php://output');
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
