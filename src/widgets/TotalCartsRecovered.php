<?php
namespace mediabeastnz\abandonedcart\widgets;

use mediabeastnz\abandonedcart\stats\TotalCartsRecovered as TotalCartsRecoveredStat;

use Craft;
use craft\base\Widget;
use craft\commerce\Plugin;
use craft\commerce\web\assets\statwidgets\StatWidgetsAsset;
use craft\helpers\DateTimeHelper;
use craft\helpers\StringHelper;

/**
 * Total Carts Recovered
 */
class TotalCartsRecovered extends Widget
{
    /**
     * @var int|\DateTime|null
     */
    public $startDate;

    /**
     * @var int|\DateTime|null
     */
    public $endDate;

    /**
     * @var string|null
     */
    public $dateRange;

    /**
     * @var null|AverageOrderTotalStat
     */
    private $_stat;

    /**
     * @inheritDoc
     */
    public function init(): void
    {
        parent::init();

        $this->dateRange = !$this->dateRange ? TotalCartsRecoveredStat::DATE_RANGE_TODAY : $this->dateRange;

        $this->_stat = new TotalCartsRecoveredStat(
            $this->dateRange,
            DateTimeHelper::toDateTime($this->startDate),
            DateTimeHelper::toDateTime($this->endDate)
        );
    }

    /**
     * @inheritdoc
     */
    public static function isSelectable(): bool
    {
        return Craft::$app->getUser()->checkPermission('commerce-manageOrders');
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('abandoned-cart', 'Abandoned Carts Total');
    }

    /**
     * @inheritdoc
     */
    public static function icon(): ?string
    {
        return Craft::getAlias('@craft/commerce/icon-mask.svg');
    }

    /**
     * @inheritdoc
     */
    public function getTitle(): ?string
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function getBodyHtml(): ?string
    {
        $number = $this->_stat->get();
        $timeFrame = $this->_stat->getDateRangeWording();

        $view = Craft::$app->getView();
        $view->registerAssetBundle(StatWidgetsAsset::class);

        return $view->renderTemplate('abandoned-cart/widgets/totalcartsrecovered/body', compact('number', 'timeFrame'));
    }

    /**
     * @inheritDoc
     */
    public static function maxColspan(): ?int
    {
        return 1;
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        $id = 'total-carts-recovered' . StringHelper::randomString();
        $namespaceId = Craft::$app->getView()->namespaceInputId($id);

        return Craft::$app->getView()->renderTemplate('commerce/_components/widgets/orders/average/settings', [
            'id' => $id,
            'namespaceId' => $namespaceId,
            'widget' => $this,
        ]);
    }
}
