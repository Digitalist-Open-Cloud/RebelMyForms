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
use Piwik\Plugins\CoreVisualizations\Visualizations\JqplotGraph\Evolution;
use Piwik\Plugins\CoreVisualizations\Visualizations\Sparklines;
use Piwik\Plugins\RebelMyForms\Categories\FormsCategory;
use Piwik\Plugins\RebelMyForms\Metrics\Ratio;
use Piwik\Report\ReportWidgetFactory;
use Piwik\Widget\WidgetsList;

class Get extends Report
{
    protected function init()
    {
        parent::init();

        $this->name = Piwik::translate('RebelMyForms_FormsOverTime');
        $this->documentation = Piwik::translate('RebelMyForms_FormsOverTimeDocumentation');
        $this->categoryId = FormsCategory::ID;
        $this->subcategoryId = 'General_Overview';
        $this->metrics = ['nb_form_views', 'nb_form_starts', 'nb_form_submissions', 'nb_form_successes'];
        $this->processedMetrics = [Ratio::startRate(), Ratio::submitRate()];
        $this->order = 1;
    }

    public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        $widgetsList->addWidgetConfig($factory->createWidget()->forceViewDataTable(Evolution::ID)->setIsWide()->setOrder(1));
        $widgetsList->addWidgetConfig(
            $factory->createWidget()
                ->setName('RebelMyForms_FormsInNumbers')
                ->forceViewDataTable(Sparklines::ID)
                ->setIsWide()
                ->setOrder(2)
        );
    }

    public function getDefaultTypeViewDataTable()
    {
        return Evolution::ID;
    }

    public function configureView(ViewDataTable $view)
    {
        if ($view instanceof Sparklines) {
            foreach ($this->getAllMetrics() as $order => $metric) {
                $view->config->addSparklineMetric([$metric], $order);
            }

            return;
        }

        $view->config->columns_to_display = ['nb_form_submissions'];
        $view->config->selectable_columns = $this->getAllMetrics();
    }
}
