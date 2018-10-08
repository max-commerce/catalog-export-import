<?php

namespace maxcom\catalog\exportimport\models;

use yii\base\Model;
use yii\web\UploadedFile;

class Import extends Model
{
    /**
     * @var UploadedFile
     */
    public $importFile;

    public function rules()
    {
        return [
            [['importFile'], 'file', 'skipOnEmpty' => false, 'extensions' => 'csv'],
        ];
    }
}