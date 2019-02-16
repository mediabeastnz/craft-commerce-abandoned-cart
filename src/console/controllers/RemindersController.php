<?php
/**
 * @copyright Copyright (c) Myles Derham.
 * @license https://craftcms.github.io/license/
 */

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
        $this->stdout('Abandoned Cart: Finding carts' . PHP_EOL, Console::FG_YELLOW);
        $res = AbandonedCart::$plugin->carts->getEmailsToSend();
        if ($res > 0) {
            $this->stdout('Carts Found: ' . $res . PHP_EOL, Console::FG_GREEN);
        } else {
            $this->stdout('No carts were found' . PHP_EOL, Console::FG_RED);
        }
        $this->stdout('Abandoned Cart: Job completed' . PHP_EOL, Console::FG_YELLOW);
        return ExitCode::OK;
    }

    // Protected Methods
    // =========================================================================
}
