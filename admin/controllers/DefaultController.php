<?php

namespace maxcom\catalog\exportimport\admin\controllers;

use maxcom\catalog\exportimport\actions\ExportAction;
use maxcom\catalog\exportimport\actions\ImportAction;
use yii\web\Controller;

class DefaultController extends Controller {

    public $defaultAction = 'import';

    public function actions()
    {
        return [
            'export' => [
                'class' => ExportAction::class,
            ],
            'import' => [
                'class' => ImportAction::class,
            ],
        ];
    }
}