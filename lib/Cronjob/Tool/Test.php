<?php
use RdsSystem\Message;

/**
 * @example dev/services/rds/misc/tools/runner.php --tool=Test -vv
 */
class Cronjob_Tool_Test extends Cronjob\Tool\ToolBase
{
    /**
     * Use this function to get command line spec for cronjob
     * @return array
     */
    public static function getCommandLineSpec()
    {
        return array(
            'action' => [
                'desc' => '',
                'useForBaseName' => true,
                'valueRequired' => true,
            ],
        );
    }


    /**
     * Performs actual work
     */
    public function run(\Cronjob\ICronjob $cronJob)
    {
        $rdsSystem = new RdsSystem\Factory($this->debugLogger);
        $model  = $rdsSystem->getMessagingRdsMsModel();

        if ($cronJob->getOption('action') == 'send') {
            $this->debugLogger->message("Sended");
            $model->sendTaskStatusChanged(new Message\TaskStatusChanged(12, 'success'));
        }
    }
}
