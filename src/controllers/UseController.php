<?php

class UseController extends Controller
{
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
	 * Lists all models.
	 */
	public function actionCreate($id)
	{
        $releaseRequest = $this->loadModel($id);
        if (!$releaseRequest->canBeUsed()) {
            throw new CHttpException(500,'Wrong release request status');
        }

        if ($releaseRequest->canByUsedImmediately()) {
            $releaseRequest->rr_status = \ReleaseRequest::STATUS_USING;
            $releaseRequest->save();
            $this->redirect('/');
        }

        $code1 = substr(md5(uniqid()), 0, 6);
        $code2 = substr(md5(uniqid()), 0, 6);
        $releaseRequest->rr_project_owner_code = $code1;
        $releaseRequest->rr_release_engineer_code = $code2;
        $releaseRequest->rr_project_owner_code_entered = false;
        $releaseRequest->rr_release_engineer_code_entered = false;
        $releaseRequest->rr_status = \ReleaseRequest::STATUS_CODES;

        $text ="Use {$releaseRequest->project->project_name} v.{$releaseRequest->rr_build_version}. Code: %s";
        Yii::app()->whotrades->{'getFinamTenderSystemFactory.getSmsSender.sendSms'}(Yii::app()->user->phone, sprintf($text, $code1));
        foreach (explode(",", \Yii::app()->params['notify']['releaseEngineers']['phones']) as $phone) {
            Yii::app()->whotrades->{'getFinamTenderSystemFactory.getSmsSender.sendSms'}($phone, sprintf($text, $code2));
        }

        $releaseRequest->save();

        $this->redirect($this->createUrl('/use/index', array('id' => $id)));
	}
	/**
	 * Lists all models.
	 */
	public function actionIndex($id)
	{
        $releaseRequest = $this->loadModel($id);
        if ($releaseRequest->rr_status != \ReleaseRequest::STATUS_CODES) {
            $this->redirect('/');
        }

        $model = new ReleaseRequest('use');
        if (isset($_POST['ReleaseRequest'])) {
            $model->attributes = $_POST['ReleaseRequest'];
            if ($model->rr_project_owner_code == $releaseRequest->rr_project_owner_code) {
                $releaseRequest->rr_project_owner_code_entered = true;
            }
            if ($model->rr_release_engineer_code == $releaseRequest->rr_release_engineer_code) {
                $releaseRequest->rr_release_engineer_code_entered = true;
            }
            if ($releaseRequest->rr_project_owner_code_entered && $releaseRequest->rr_release_engineer_code_entered) {
                $releaseRequest->rr_status = \ReleaseRequest::STATUS_USING;
            }
            $releaseRequest->save();
            $this->redirect('/');
        }
        $this->render('index', array(
            'model' => $model,
            'releaseRequest' => $releaseRequest,
        ));
	}

    /**
     * @param $id
     *
     * @return ReleaseRequest
     * @throws CHttpException
     */
    public function loadModel($id)
    {
        $model=ReleaseRequest::model()->findByPk($id);
        if($model===null)
            throw new CHttpException(404,'The requested page does not exist.');
        return $model;
    }
}