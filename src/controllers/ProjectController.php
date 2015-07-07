<?php

class ProjectController extends Controller
{
    public $pageTitle = 'Проекты';

	/**
	 * @return array action filters
	 */
	public function filters()
	{
		return array(
			'accessControl', // perform access control for CRUD operations
		);
	}

	/**
	 * Specifies the access control rules.
	 * This method is used by the 'accessControl' filter.
	 * @return array access control rules
	 */
	public function accessRules()
	{
		return array(
			array('allow',  // allow all users to perform 'index' and 'view' actions
				'users'=>array('@'),
			),
			array('deny',  // deny all users
				'users'=>array('*'),
			),
		);
	}

	/**
	 * Displays a particular model.
	 * @param integer $id the ID of the model to be displayed
	 */
	public function actionView($id)
	{
        $configHistoryModel = ProjectConfigHistory::model();
        $configHistoryModel->pch_project_obj_id = $id;
		$this->render('view',array(
			'model'             =>$this->loadModel($id),
			'configHistoryModel'=> $configHistoryModel,
		));
	}

	/**
	 * Creates a new model.
	 * If creation is successful, the browser will be redirected to the 'view' page.
	 */
	public function actionCreate()
	{
		$model=new Project;

		if(isset($_POST['Project']))
		{
			$model->attributes=$_POST['Project'];
			if($model->save()) {
                foreach ($_POST['workers'] as $workerId) {
                    $p2w = new Project2worker();
                    $p2w->worker_obj_id = $workerId;
                    $p2w->project_obj_id = $model->obj_id;
                    $p2w->save();
                }

				$this->redirect(array('view','id'=>$model->obj_id));
            }
		}


        $list = array();
        foreach ($model->project2workers as $val) {
            $list[$val->worker_obj_id] = $val;
        }

		$this->render('create',array(
			'model'=>$model,
			'list'=>$list,
			'workers'=>Worker::model()->findAll(),
		));
	}

	/**
	 * Updates a particular model.
	 * If update is successful, the browser will be redirected to the 'view' page.
	 * @param integer $id the ID of the model to be updated
	 */
	public function actionUpdate($id)
	{
		$model=$this->loadModel($id);

		if(isset($_POST['Project']))
		{
			$model->attributes=$_POST['Project'];
            $model->project_config = str_replace("\r", "", $model->project_config);
            $transaction = $model->getDbConnection()->beginTransaction();
            $existingProject = Project::model()->findByPk($model->obj_id);

			if($model->save()) {
                Log::createLogMessage("Удалены все связки {$model->project_name}");
                Project2worker::model()->deleteAllByAttributes(array('project_obj_id' => $model->obj_id));
                foreach ($_POST['workers'] as $workerId) {
                    $p2w = new Project2worker();
                    $p2w->worker_obj_id = $workerId;
                    $p2w->project_obj_id = $model->obj_id;
                    $p2w->save();
                    Log::createLogMessage("Создана {$p2w->getTitle()}");
                }

                if ($model->project_config != $existingProject->project_config) {
                    $diffStat = Yii::app()->diffStat->getDiffStat(
                        str_replace("\r", "", $existingProject->project_config),
                        str_replace("\r", "", $model->project_config)
                    );
                    $diffStat = preg_replace('~\++~', '<span style="color: #32cd32">$0</span>', $diffStat);
                    $diffStat = preg_replace('~\-+~', '<span style="color: red">$0</span>', $diffStat);

                    $projectHistoryItem = new ProjectConfigHistory();
                    $projectHistoryItem->pch_project_obj_id = $model->obj_id;
                    $projectHistoryItem->pch_config = $model->project_config;
                    $projectHistoryItem->pch_user = \Yii::app()->user->name;
                    $projectHistoryItem->save();

                    (new RdsSystem\Factory(Yii::app()->debugLogger))->getMessagingRdsMsModel()->sendProjectConfig(new \RdsSystem\Message\ProjectConfig(
                        $projectHistoryItem->project->project_name, $model->project_config
                    ));

                    Log::createLogMessage("Изменение в конфигурации $existingProject->project_name:<br />
$diffStat<br />
<a href='".$this->createUrl("/diff/project_config", ['id' => $projectHistoryItem->obj_id])."'>Посмотреть подробнее</a>
");
                }
                if (!$model->hasErrors()) {
                    $transaction->commit();
				    $this->redirect(array('view','id'=>$model->obj_id));
                } else {
                    $transaction->rollback();
                }
            } else {
                $transaction->rollback();
            }
		}

        $list = array();
        foreach ($model->project2workers as $val) {
            $list[$val->worker_obj_id] = $val;
        }

		$this->render('update',array(
			'model'=>$model,
            'list'=>$list,
            'workers'=>Worker::model()->findAll(),
		));
	}

	/**
	 * Deletes a particular model.
	 * If deletion is successful, the browser will be redirected to the 'admin' page.
	 * @param integer $id the ID of the model to be deleted
	 */
	public function actionDelete($id)
	{
		$this->loadModel($id)->delete();

		// if AJAX request (triggered by deletion via admin grid view), we should not redirect the browser
		if(!isset($_GET['ajax']))
			$this->redirect(isset($_POST['returnUrl']) ? $_POST['returnUrl'] : array('admin'));
	}

	/**
	 * Lists all models.
	 */
	public function actionIndex()
	{
		$dataProvider=new CActiveDataProvider('Project');
		$this->render('index',array(
			'dataProvider'=>$dataProvider,
		));
	}

	/**
	 * Manages all models.
	 */
	public function actionAdmin()
	{
		$model=new Project('search');
		$model->unsetAttributes();  // clear any default values
		if(isset($_GET['Project']))
			$model->attributes=$_GET['Project'];

		$this->render('admin',array(
			'model'=>$model,
            'workers'=>Worker::model()->findAll(),
		));
	}

	/**
	 * Returns the data model based on the primary key given in the GET variable.
	 * If the data model is not found, an HTTP exception will be raised.
	 * @param integer $id the ID of the model to be loaded
	 * @return Project the loaded model
	 * @throws CHttpException
	 */
	public function loadModel($id)
	{
		$model=Project::model()->findByPk($id);
		if($model===null)
			throw new CHttpException(404,'The requested page does not exist.');
		return $model;
	}

	/**
	 * Performs the AJAX validation.
	 * @param Project $model the model to be validated
	 */
	protected function performAjaxValidation($model)
	{
		if(isset($_POST['ajax']) && $_POST['ajax']==='project-form')
		{
			echo CActiveForm::validate($model);
			Yii::app()->end();
		}
	}
}
