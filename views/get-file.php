<?php $form = \yii\widgets\ActiveForm::begin() ?>
        <?=$form->field($model,'importFile')->fileInput();?>
        <?='' /*$form->field($model,'flushMainTable')->checkbox(); */ ?>
        <?=\yii\helpers\Html::submitButton('Импортировать', ['class' => 'btn btn-success'])?>
<?php \yii\widgets\ActiveForm::end()?>
