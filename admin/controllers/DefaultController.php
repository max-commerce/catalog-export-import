<?php

namespace maxcom\catalog\exportimport\admin\controllers;

use maxcom\catalog\exportimport\actions\ExportAction;
use maxcom\catalog\exportimport\actions\ImportAction;
use yii\filters\AccessControl;
use yii\web\Controller;

class DefaultController extends Controller {
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => $this->module->permissions,
                    ],
                ],
            ],
        ];
    }

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