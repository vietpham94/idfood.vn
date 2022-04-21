<?php
require_once ABSPATH . 'vendor/a5hleyrich/wp-background-processing/classes/wp-async-request.php';
require_once ABSPATH . 'vendor/a5hleyrich/wp-background-processing/classes/wp-background-process.php';

class WC_IdFood_Background_Process extends WP_Background_Process
{

    protected $action = 'wc_idfood_background_process';

    /**
     * Task
     *
     * Override this method to perform any actions required on each
     * queue item. Return the modified item for further processing
     * in the next pass through. Or, return false to remove the
     * item from the queue.
     *
     * @param mixed $item Queue item to iterate over
     *
     * @return mixed
     */
    protected function task($item)
    {
        error_log($item);

        sleep(5);

        return false;
    }

    /**
     * Complete
     *
     * Override if applicable, but ensure that the below actions are
     * performed, or, call parent::complete().
     */
    protected function complete()
    {
        parent::complete();
    }
}