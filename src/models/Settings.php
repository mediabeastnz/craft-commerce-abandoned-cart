<?php
/**
 * @copyright Copyright (c) Myles Derham.
 * @license https://craftcms.github.io/license/
 */

namespace mediabeastnz\abandonedcart\models;

use craft\base\Model;

class Settings extends Model
{
    public $pluginName = 'Abandoned Carts';

    public $restoreExpiryHours = '48';

    public $firstReminderDelay = '1';

    public $secondReminderDelay = '12';

    public $discountCode = "";

    public $firstReminderTemplate = 'abandoned-cart/emails/first';

    public $secondReminderTemplate = 'abandoned-cart/emails/second';

    public $firstReminderSubject = "You've left some items in your cart";

    public $secondReminderSubject = "Your items are still waiting - don't miss out";

    public function rules()
    {
        return [
            [[
                'pluginName',
                'restoreExpiryHours', 
                'firstReminderDelay', 
                'secondReminderDelay',
                'firstReminderTemplate',
                'secondReminderTemplate',
                'firstReminderSubject', 
                'secondReminderSubject'
            ], 'required'],
            ['restoreExpiryHours', 'integer', 'min' => 24, 'max' => '168'], // Atleast 24hrs
            ['firstReminderDelay', 'integer', 'min' => 1, 'max' => 24], // 1hr +
            ['secondReminderDelay', 'integer', 'min' => 12, 'max' => 48], // prevent spam
            [['firstReminderTemplate', 'secondReminderTemplate'], 'string'],
            [['firstReminderSubject', 'secondReminderSubject'], 'string'],
        ];
    }
}