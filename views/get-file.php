<?php $form = \yii\widgets\ActiveForm::begin() ?>
        <?=$form->field($model,'importFile')->fileInput();?>
        <?=\yii\helpers\Html::submitButton('Импортировать', ['class' => 'btn btn-success'])?>
<?php \yii\widgets\ActiveForm::end()?>
