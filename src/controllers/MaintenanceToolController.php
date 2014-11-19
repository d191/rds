<?php
class MaintenanceToolController extends Controller
{
    public function filters()
    {
        return array(
            'accessControl'
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
            array('allow', // allow authenticated user to perform 'create' and 'update' actions
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
        $this->render('view',array(
            'model'=>$this->loadModel($id),
        ));
    }

    public function actionIndex()
    {
        $model=new MaintenanceTool('search');
        $model->unsetAttributes();  // clear any default values
        if(isset($_GET['MaintenanceTool']))
            $model->attributes=$_GET['MaintenanceTool'];

        $this->render('index',array(
            'model'=>$model,
        ));
    }

    public function actionStart($id)
    {
        /** @var $tool MaintenanceTool */
        $tool = $this->loadModel($id);

        $mtr = $tool->start(Yii::app()->user->name);

        if (empty($mtr->errors)) {
            $this->redirect($this->createUrl('/maintenanceToolRun/view/', ['id' => $mtr->obj_id]));
        } else {
            throw new Exception("Can't  create new instance of tool: ".json_encode($mtr->errors));
        }
    }

    public function actionStop($id)
    {
        /** @var $tool MaintenanceTool */
        $tool = $this->loadModel($id);

        $tool->stop(Yii::app()->user->name);

        $this->redirect($this->createUrl('/maintenanceTool/'));
    }

    /**
     * Returns the data model based on the primary key given in the GET variable.
     * If the data model is not found, an HTTP exception will be raised.
     * @param integer $id the ID of the model to be loaded
     * @return MaintenanceTool the loaded model
     * @throws CHttpException
     */
    public function loadModel($id)
    {
        $model=MaintenanceTool::model()->findByPk($id);
        if($model===null)
            throw new CHttpException(404,'The requested page does not exist.');
        return $model;
    }

    /**
     * Performs the AJAX validation.
     * @param MaintenanceTool $model the model to be validated
     */
    protected function performAjaxValidation($model)
    {
        if(isset($_POST['ajax']) && $_POST['ajax']==='maintenance-tool-form')
        {
            echo CActiveForm::validate($model);
            Yii::app()->end();
        }
    }
}