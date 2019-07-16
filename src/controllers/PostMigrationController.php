<?php
/**
 * @author Anton Gorlanov <antonxacc@gmail.com>
 */
namespace whotrades\rds\controllers;

use whotrades\rds\models\PostMigration;
use whotrades\rds\models\ReleaseRequest;
use whotrades\rds\models\Build;

class PostMigrationController extends ControllerRestrictedBase
{
    public $pageTitle = 'Post Migrations';

    /**
     * @return string
     */
    public function actionIndex()
    {
        $model = new PostMigration(['scenario' => 'search']);
        if (isset($_GET['Log'])) {
            $model->attributes = $_GET['Log'];
        }

        $postMigrationAllowTimestamp = strtotime("-" . \Yii::$app->params['postMigrationStabilizeDelay']);

        return $this->render('admin', array(
            'model' => $model,
            'postMigrationAllowTimestamp' => $postMigrationAllowTimestamp,
        ));
    }

    /**
     * @param int $postMigrationId
     */
    public function actionApply($postMigrationId)
    {
        /** @var PostMigration $postMigration */
        $postMigration = PostMigration::findByPk($postMigrationId);

        if ($postMigration->pm_status === PostMigration::STATUS_APPLIED) {
            \Yii::info("Post migration {$postMigration->pm_name} is applied already");

            $this->redirect('/postMigration/index');
        }

        $postMigrationAllowTimestamp = strtotime("-" . \Yii::$app->params['postMigrationStabilizeDelay']);
        $waitingTime = (new \DateTime($postMigration->obj_created))->getTimestamp() - $postMigrationAllowTimestamp;

        if ($waitingTime > 0) {
            $waitingDays = ceil($waitingTime / (24 * 60 * 60));

            \Yii::info("Post migration {$postMigration->pm_name} is waiting {$waitingDays} days for applying");

            $this->redirect('/postMigration/index');
        }

        $releaseRequestCurrent = ReleaseRequest::find()->
            andWhere(['rr_project_obj_id' => $postMigration->project->obj_id])->
            andWhere(['rr_status' => 'used'])->
            orderBy('obj_id desc')->
            one();

        /** @var Build $build */
        foreach ($releaseRequestCurrent->builds as $build) {
            (new \whotrades\RdsSystem\Factory())->
            getMessagingRdsMsModel()->
            sendMigrationTask(
                $build->worker->worker_name,
                new \whotrades\RdsSystem\Message\MigrationTask(
                    $releaseRequestCurrent->project->project_name,
                    $releaseRequestCurrent->rr_build_version,
                    'post',
                    $postMigration->project->script_migration_up,
                    $postMigration->pm_name
                )
            );
        }

        $postMigration->pm_status = PostMigration::STATUS_STARTED;
        $postMigration->save();

        $this->redirect('/postMigration/index');
    }

    /**
     * @param int $postMigrationId
     *
     * @return string
     */
    public function actionViewLog($postMigrationId)
    {
        /** @var PostMigration $postMigration */
        $postMigration = PostMigration::findByPk($postMigrationId);

        if (!$postMigration) {
            return 'There is not post migration with id ' . $postMigrationId;
        }

        return $postMigration->pm_log;
    }
}