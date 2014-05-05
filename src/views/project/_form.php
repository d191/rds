<?php
/* @var $this ProjectController */
/* @var $model Project */
/* @var $form TbActiveForm */
?>

<div class="form">

<?php $form=$this->beginWidget('bootstrap.widgets.TbActiveForm', array(
	'id'=>'project-form',
	'enableAjaxValidation'=>false,
)); ?>

	<p class="note">Fields with <span class="required">*</span> are required.</p>

	<?php echo $form->errorSummary($model); ?>

    <?php echo $form->textFieldRow($model,'project_name'); ?>


    <br />
    Собирать на:<br />
    <?foreach ($workers as $worker) {?>
        <?=CHtml::checkBox('workers[]', !empty($list[$worker->obj_id]), array('id' => $id = uniqid(), 'value' => $worker->obj_id))?>
        <label style="display: inline" for="<?=$id?>"><?=$worker->worker_name?></label>
        <br />
    <?}?>

    <br />
	<div class="row buttons">
		<?php echo CHtml::submitButton($model->isNewRecord ? 'Create' : 'Save'); ?>
	</div>

<?php $this->endWidget(); ?>

</div><!-- form -->