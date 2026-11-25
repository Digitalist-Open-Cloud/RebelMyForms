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
use Piwik\Plugins\RebelMyForms\Metrics\Ratio;

class GetFields extends Report
{
    protected function init()
    {
        parent::init();

        $this->name = Piwik::translate('RebelMyForms_Fields');
        $this->documentation = Piwik::translate('RebelMyForms_FieldsDocumentation');
        $this->categoryId = FormsCategory::ID;
        $this->metrics = ['nb_field_interactions', 'nb_field_changes', 'nb_field_refocuses', 'nb_field_blank', 'nb_field_invalid', 'nb_field_dropoffs'];
        $this->processedMetrics = [
            new Ratio('avg_field_time', 'sum_field_time', 'nb_field_interactions', true),
            new Ratio('field_dropoff_rate', 'nb_field_dropoffs', 'nb_field_interactions'),
        ];
        $this->defaultSortColumn = 'nb_field_interactions';
        $this->isSubtableReport = true;
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->columns_to_display = [
            'label',
            'nb_field_interactions',
            'avg_field_time',
            'nb_field_changes',
            'nb_field_refocuses',
            'nb_field_blank',
            'nb_field_invalid',
            'nb_field_dropoffs',
            'field_dropoff_rate',
        ];
        $view->config->addTranslation('label', Piwik::translate('RebelMyForms_ColumnField'));
        // Matomo only finds the texts of a report that is not under another.
        $view->config->metrics_documentation = $this->getMetricsDocumentation();

        $view->config->show_all_views_icons = false;
        $view->config->show_exclude_low_population = false;
        $view->config->disable_row_evolution = true;
    }
}
