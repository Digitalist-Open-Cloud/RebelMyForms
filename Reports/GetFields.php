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
        $this->metrics = ['field_position', 'nb_field_interactions', 'nb_field_changes', 'nb_field_refocuses', 'nb_field_blank', 'nb_field_invalid', 'nb_field_dropoffs'];
        $this->processedMetrics = Ratio::ofFields();
        // From the top of the form to its end.
        $this->defaultSortColumn = 'field_position';
        $this->defaultSortOrderDesc = false;
        $this->isSubtableReport = true;
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->columns_to_display = [
            'label',
            'field_position',
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
        $view->config->addTranslation('label', Piwik::translate('RebelMyForms_ColumnField'));
        // Matomo only finds the texts of a report that is not under another.
        $view->config->metrics_documentation = $this->getMetricsDocumentation();

        $view->requestConfig->filter_sort_column = 'field_position';
        $view->requestConfig->filter_sort_order = 'asc';

        $view->config->show_all_views_icons = false;
        $view->config->show_exclude_low_population = false;
        $view->config->disable_row_evolution = true;
    }
}
