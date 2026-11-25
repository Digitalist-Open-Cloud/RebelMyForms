<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelMyForms\Reports;

use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\RebelMyForms\Categories\FormsCategory;
use Piwik\Plugins\RebelMyForms\Columns\FormField;
use Piwik\Plugins\RebelMyForms\Metrics\Ratio;
use Piwik\Report\ReportWidgetFactory;
use Piwik\Widget\WidgetsList;

class GetAllFields extends Report
{
    protected function init()
    {
        parent::init();

        $this->name = Piwik::translate('RebelMyForms_FieldsOfAllForms');
        $this->documentation = Piwik::translate('RebelMyForms_FieldsOfAllFormsDocumentation');
        $this->categoryId = FormsCategory::ID;
        $this->dimension = new FormField();
        $this->subcategoryId = 'General_Overview';
        $this->metrics = ['nb_field_interactions', 'nb_field_changes', 'nb_field_refocuses', 'nb_field_blank', 'nb_field_invalid', 'nb_field_dropoffs'];
        $this->processedMetrics = Ratio::ofFields();
        $this->defaultSortColumn = 'nb_field_dropoffs';
        $this->order = 6;
    }

    public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        $widgetsList->addWidgetConfig($factory->createWidget()->setIsWide());
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->columns_to_display = [
            'label',
            'nb_field_interactions',
            'avg_field_time',
            'avg_field_hesitation',
            'nb_field_changes',
            'nb_field_refocuses',
            'nb_field_blank',
            'nb_field_invalid',
            'nb_field_dropoffs',
            'field_dropoff_rate',
        ];

        // The fields where most visitors gave up come first.
        $view->requestConfig->filter_sort_column = 'nb_field_dropoffs';
        $view->requestConfig->filter_sort_order = 'desc';

        $view->config->show_exclude_low_population = false;
    }
}
