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
use Piwik\Plugins\RebelMyForms\Columns\FormName;
use Piwik\Plugins\RebelMyForms\Metrics\Ratio;
use Piwik\Report\ReportWidgetFactory;
use Piwik\Widget\WidgetsList;

class GetForms extends Report
{
    protected function init()
    {
        parent::init();

        $this->name = Piwik::translate('RebelMyForms_Forms');
        $this->documentation = Piwik::translate('RebelMyForms_FormsReportDocumentation');
        $this->categoryId = FormsCategory::ID;
        $this->dimension = new FormName();
        $this->subcategoryId = 'General_Overview';
        $this->metrics = ['nb_form_views', 'nb_form_starts', 'nb_form_submissions'];
        $this->processedMetrics = [Ratio::startRate(), Ratio::submitRate(), new Ratio('avg_form_time', 'sum_form_time', 'nb_form_starts', true)];
        $this->defaultSortColumn = 'nb_form_views';
        $this->actionToLoadSubTables = 'getFields';
        $this->supportsFlatten = false;
        $this->order = 3;
    }

    public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        $widgetsList->addWidgetConfig($factory->createWidget()->setIsWide());
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->columns_to_display = ['label', 'nb_form_views', 'nb_form_starts', 'form_start_rate', 'nb_form_submissions', 'form_submit_rate', 'avg_form_time'];
        $view->config->addTranslation('label', Piwik::translate('RebelMyForms_ColumnForm'));

        $view->config->show_all_views_icons = false;
        $view->config->show_exclude_low_population = false;
        $view->config->show_flatten_table = false;
    }
}
