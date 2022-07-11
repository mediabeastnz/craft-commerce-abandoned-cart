<?php
/**
 * @copyright Copyright (c) Myles Derham.
 * @license https://craftcms.github.io/license/
 */

namespace mediabeastnz\abandonedcart\models;

use craft\base\Model;
use craft\helpers\StringHelper;
use craft\behaviors\EnvAttributeParserBehavior;

class Settings extends Model
{
    public $pluginName = 'Abandoned Carts';

    public $testMode = false;

    public $passKey;

    public $restoreExpiryHours = '48';

    public $firstReminderDelay = '1';

    public $secondReminderDelay = '12';

    public $discountCode;

    public $firstReminderTemplate = 'abandoned-cart/emails/first';

    public $secondReminderTemplate = 'abandoned-cart/emails/second';

    public $firstReminderSubject = "You've left some items in your cart";

    public $secondReminderSubject = "Your items are still waiting - don't miss out";

    public $recoveryUrl = "shop/cart";

    public $disableSecondReminder = false;

    public $blacklist = false;

    public function __construct() 
    {
        if (empty($this->passKey)) {
            $this->passKey = StringHelper::randomString(15);
        }
    }

    public function getTestMode(): string
    {
        return Craft::parseEnv($this->testMode);
    }

    public function getPassKey(): string
    {
        return Craft::parseEnv($this->passKey);
    }

    public function getPluginName(): string
    {
        return Craft::parseEnv($this->pluginName);
    }

    public function getDisableSecondReminder(): string
    {
        return Craft::parseEnv($this->disableSecondReminder);
    }

    public function getRestoreExpiryHours(): string
    {
        return Craft::parseEnv($this->restoreExpiryHours);
    }

    public function getFirstReminderDelay(): string
    {
        return Craft::parseEnv($this->firstReminderDelay);
    }

    public function getSecondReminderDelay(): string
    {
        return Craft::parseEnv($this->secondReminderDelay);
    }

    public function getFirstReminderTemplate(): string
    {
        return Craft::parseEnv($this->firstReminderTemplate);
    }

    public function getFirstReminderSubject(): string
    {
        return Craft::parseEnv($this->firstReminderSubject);
    }

    public function getSecondReminderTemplate(): string
    {
        return Craft::parseEnv($this->secondReminderTemplate);
    }

    public function getSecondReminderSubject(): string
    {
        return Craft::parseEnv($this->secondReminderSubject);
    }

    public function getDiscountCode(): string
    {
        return Craft::parseEnv($this->discountCode);
    }

    public function getRecoveryUrl(): string
    {
        return Craft::parseEnv($this->recoveryUrl);
    }

    public function getBlacklist(): string
    {
        return Craft::parseEnv($this->blacklist);
    }

    public function behaviors(): array
    {
        return [
            'parser' => [
                'class' => EnvAttributeParserBehavior::class,
                'attributes' => [
                    'passKey',
                    'restoreExpiryHours',
                    'firstReminderDelay',
                    'secondReminderDelay',
                    'firstReminderTemplate',
                    'firstReminderSubject',
                ],
            ],
        ];
    }

    public function rules(): array
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
                'secondReminderSubject',
                'recoveryUrl',
                'passKey'
            ], 'required'],
            ['restoreExpiryHours', 'integer', 'min' => 24, 'max' => '168'], // Atleast 24hrs
            ['firstReminderDelay', 'integer', 'min' => 1, 'max' => 24], // 1hr +
            ['secondReminderDelay', 'integer', 'min' => 12, 'max' => 48], // prevent spam
            [['firstReminderTemplate', 'secondReminderTemplate'], 'string'],
            [['firstReminderSubject', 'secondReminderSubject'], 'string'],
        ];
    }

}