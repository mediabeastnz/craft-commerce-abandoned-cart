<?php

namespace mediabeastnz\abandonedcart\models;

use craft\base\Model;

class Settings extends Model
{
    public $pluginName = 'Abandoned Carts';

    public $firstReminderDelay = '1';

    public $secondReminderDelay = '24';

    public $firstReminderTemplate = 'abandoned-cart/emails/first';

    public $secondReminderTemplate = 'abandoned-cart/emails/second';

    public function rules()
    {
        return [
            [['pluginName', 'firstReminderDelay', 'secondReminderDelay'], 'required'],
            ['firstReminderDelay', 'integer', 'min' => 1], // 1hr +
            ['secondReminderDelay', 'integer', 'min' => 12], // prevent spam
            [['firstReminderTemplate', 'secondReminderTemplate'], 'string'],
        ];
    }
}