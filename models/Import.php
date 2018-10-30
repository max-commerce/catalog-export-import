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

    public $flushMainTable;

    public function rules()
    {
        return [
            [['flushMainTable'], 'integer'],
            [['importFile'], 'file', 'skipOnEmpty' => false, 'extensions' => 'csv'],
        ];
    }
}