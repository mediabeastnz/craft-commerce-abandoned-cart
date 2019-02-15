<?php
namespace mediabeastnz\abandonedcart\console\controllers;

use mediabeastnz\abandonedcart\AbandonedCart;

use Craft;
use craft\helpers\Console;

use yii\console\ExitCode;


use yii\console\Controller;

/**
 * Abandoned Cart CLI.
 *
 * @author Myles Derham. <myles.derham@gmail.com>
 */

class RemindersController extends Controller
{

    // Properties
    // =========================================================================
    
    public $defaultAction = 'scheduleEmails';

    // Public Methods
    // =========================================================================

    /**
     * @param string $actionID
     *
     * @return array|string[]
     */
    public function options($actionID): array
    {
        return [];
    }

    /**
     * Finds all abandoned carts and sends reminder
     *
     * @return int
     */
    public function actionScheduleEmails()
    {
        $this->stdout('Abandoned Cart Job Started....' . PHP_EOL, Console::FG_GREEN);
        $res = AbandonedCart::$plugin->carts->getEmailsToSend();
        $this->stdout('Abandoned Carts Found: ' . $res . PHP_EOL, Console::FG_GREEN);
        return ExitCode::OK;
    }

    // Protected Methods
    // =========================================================================
}
