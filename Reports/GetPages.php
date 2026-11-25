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
use Piwik\Plugins\RebelMyForms\Columns\FormPage;
use Piwik\Plugins\RebelMyForms\Metrics\Ratio;
use Piwik\Report\ReportWidgetFactory;
use Piwik\Widget\WidgetsList;

class GetPages extends Report
{
    protected function init()
    {
        parent::init();

        $this->name = Piwik::translate('RebelMyForms_FormsByPage');
        $this->documentation = Piwik::translate('RebelMyForms_FormsByPageDocumentation');
        $this->categoryId = FormsCategory::ID;
        $this->dimension = new FormPage();
        $this->subcategoryId = 'General_Overview';
        $this->metrics = ['nb_form_views', 'nb_form_starts', 'nb_form_submissions'];
        $this->processedMetrics = [Ratio::startRate(), Ratio::submitRate()];
        $this->defaultSortColumn = 'nb_form_views';
        $this->order = 4;
    }

    public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        // An address needs the width.
        $widgetsList->addWidgetConfig($factory->createWidget()->setIsWide());
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->columns_to_display = ['label', 'nb_form_views', 'nb_form_starts', 'form_start_rate', 'nb_form_submissions', 'form_submit_rate'];
        $view->config->show_exclude_low_population = false;
    }
}
