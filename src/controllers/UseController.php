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
                'users' => array('@'),
            ),
            array('deny',  // deny all users
                'users' => array('*'),
            ),
        );
    }

    /**
     * @param int $id
     * Lists all models.
     */
    public function actionCreate($id)
    {
        $releaseRequest = $this->loadModel($id);
        if (!$releaseRequest->canBeUsed()) {
            throw new CHttpException(500, 'Wrong release request status');
        }

        $deployment_enabled = RdsDbConfig::get()->deployment_enabled;
        if (!$deployment_enabled) {
            throw new CHttpException(500, 'Deployment disabled');
        }

        if ($releaseRequest->canByUsedImmediately()) {
            $slaveList = ReleaseRequest::model()->findAllByAttributes([
                'rr_leading_id' => $releaseRequest->obj_id,
                'rr_status' => [ReleaseRequest::STATUS_INSTALLED, ReleaseRequest::STATUS_OLD],
            ]);
            $releaseRequest->sendUseTasks(\Yii::app()->user->name);
            foreach ($slaveList as $slave) {
                /** @var $slave ReleaseRequest */
                $slave->sendUseTasks(\Yii::app()->user->name);
            }
            if (!empty($_GET['ajax'])) {
                echo "using";

                return;
            } else {
                $this->redirect('/');
            }
        }

        $code1 = rand(pow(10, 2), pow(10, 3) - 1);
        $code2 = rand(pow(10, 2), pow(10, 3) - 1);
        $releaseRequest->rr_project_owner_code = $code1;
        $releaseRequest->rr_release_engineer_code = $code2;
        $releaseRequest->rr_project_owner_code_entered = false;
        $releaseRequest->rr_release_engineer_code_entered = true;
        $releaseRequest->rr_status = \ReleaseRequest::STATUS_CODES;

        $text = "Code: %s. USE {$releaseRequest->project->project_name} v.{$releaseRequest->rr_build_version}";
        Yii::app()->whotrades->{'getFinamTenderSystemFactory.getSmsSender.sendSms'}(Yii::app()->user->phone, sprintf($text, $code1));

        if ($releaseRequest->save()) {
            Cronjob_Tool_AsyncReader_Deploy::sendReleaseRequestUpdated($releaseRequest->obj_id);

            $currentUsed = ReleaseRequest::model()->findByAttributes([
                'rr_project_obj_id' => $releaseRequest->rr_project_obj_id,
                'rr_status' => ReleaseRequest::STATUS_USED,
            ]);
            if ($currentUsed) {
                Cronjob_Tool_AsyncReader_Deploy::sendReleaseRequestUpdated($currentUsed->obj_id);
            }

            Log::createLogMessage("CODES {$releaseRequest->getTitle()}");
        }

        $this->redirect($this->createUrl('/use/index', ['id' => $id]));
    }

    /**
     * @param int $id
     * @param string $type
     *
     * @throws CHttpException
     * @throws Exception
     */
    public function actionMigrate($id, $type)
    {
        $releaseRequest = $this->loadModel($id);
        if (!$releaseRequest->canBeUsed()) {
            $this->redirect('/');
        }

        if ($type == 'pre') {
            $releaseRequest->rr_migration_status = \ReleaseRequest::MIGRATION_STATUS_UPDATING;
            $logMessage = "Запущены pre миграции {$releaseRequest->getTitle()}";
        } else {
            $releaseRequest->rr_post_migration_status = \ReleaseRequest::MIGRATION_STATUS_UPDATING;
            $logMessage = "Запущены post миграции {$releaseRequest->getTitle()}";
        }

        foreach ($releaseRequest->project->project2workers as $p2w) {
            /** @var Project2worker $p2w */
            $worker = $p2w->worker;
            (new RdsSystem\Factory(Yii::app()->debugLogger))->
                getMessagingRdsMsModel()->
                sendMigrationTask(
                    $worker->worker_name,
                    new \RdsSystem\Message\MigrationTask(
                        $releaseRequest->project->project_name,
                        $releaseRequest->rr_build_version,
                        $type
                    )
                );
        }

        if ($releaseRequest->save()) {
            Cronjob_Tool_AsyncReader_Deploy::sendReleaseRequestUpdated($releaseRequest->obj_id);
            Log::createLogMessage($logMessage);
        }

        $this->redirect('/');
    }

    /**
     * Проверки смс кодов
     *
     * @param $model
     * @param $releaseRequest
     */
    private function checkReleaseCode(ReleaseRequest $model, $releaseRequest)
    {
        if ($model->rr_project_owner_code == $releaseRequest->rr_project_owner_code) {
            Log::createLogMessage("Введен правильный Project Owner код {$releaseRequest->getTitle()}");
            $releaseRequest->rr_project_owner_code_entered = true;
        } else {
            $model->addError('rr_project_owner_code', "Код не подошел");
        }
        if ($model->rr_release_engineer_code == $releaseRequest->rr_release_engineer_code) {
            Log::createLogMessage("Введен правильный Release Engineer код {$releaseRequest->getTitle()}");
            $releaseRequest->rr_release_engineer_code_entered = true;
        }
    }

    /**
     * @param int $id
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

            $deployment_enabled = RdsDbConfig::get()->deployment_enabled;
            if (!$deployment_enabled) {
                $model->addError('rr_project_owner_code', 'Обновление серверов временно отключено');
            }
            // проверяем правильность ввода смс
            $this->checkReleaseCode($model, $releaseRequest);

            $this->performAjaxValidation($model);

            if ($releaseRequest->rr_project_owner_code_entered && $deployment_enabled) {
                $releaseRequest->sendUseTasks(\Yii::app()->user->name);

                $slaveList = ReleaseRequest::model()->findAllByAttributes([
                    'rr_leading_id' => $releaseRequest->obj_id,
                    'rr_status' => [ReleaseRequest::STATUS_INSTALLED, ReleaseRequest::STATUS_OLD],
                ]);
                foreach ($slaveList as $slave) {
                    /** @var $slave ReleaseRequest */
                    $slave->sendUseTasks(\Yii::app()->user->name);
                }
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
     * @param int $id
     *
     * @return ReleaseRequest
     * @throws CHttpException
     */
    public function loadModel($id)
    {
        $model = ReleaseRequest::model()->findByPk($id);
        if ($model == null) {
            throw new CHttpException(404, 'The requested page does not exist.');
        }

        return $model;
    }

    protected function performAjaxValidation($model)
    {
        if (isset($_POST['ajax']) && $_POST['ajax'] == 'release-request-use-form') {
            $result = [];
            foreach ($model->getErrors() as $attribute => $errors) {
                $result[CHtml::activeId($model, $attribute)] = $errors;
            }
            echo function_exists('json_encode') ? json_encode($result) : CJSON::encode($result);
            Yii::app()->end();
        }
    }
}
