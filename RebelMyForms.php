<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelMyForms;

use Piwik\Common;
use Piwik\Plugin;
use Piwik\Plugins\RebelMyForms\Dao\LogForm;

class RebelMyForms extends Plugin
{
    public const METRICS = [
        'nb_form_views' => 'ColumnViews',
        'nb_form_starts' => 'ColumnStarts',
        'nb_form_submissions' => 'ColumnSubmissions',
        'nb_form_resubmits' => 'ColumnResubmits',
        'nb_form_successes' => 'ColumnSuccesses',
        'form_start_rate' => 'ColumnStartRate',
        'form_submit_rate' => 'ColumnSubmitRate',
        'avg_form_wait' => 'ColumnAvgWait',
        'avg_form_time' => 'ColumnAvgFormTime',
        'field_position' => 'ColumnPosition',
        'nb_field_interactions' => 'ColumnInteractions',
        'avg_field_time' => 'ColumnAvgFieldTime',
        'avg_field_hesitation' => 'ColumnAvgHesitation',
        'nb_field_changes' => 'ColumnChanges',
        'nb_field_refocuses' => 'ColumnRefocuses',
        'nb_field_blank' => 'ColumnBlank',
        'nb_field_invalid' => 'ColumnInvalid',
        'nb_field_dropoffs' => 'ColumnDropoffs',
        'field_dropoff_rate' => 'ColumnDropoffRate',
    ];

    public function registerEvents()
    {
        return [
            'AssetManager.getStylesheetFiles' => 'getStylesheetFiles',
            'Db.getTablesInstalled' => 'getTablesInstalled',
            'Metrics.getDefaultMetricTranslations' => 'addMetricTranslations',
            'Metrics.getDefaultMetricDocumentationTranslations' => 'addMetricDocumentationTranslations',
        ];
    }

    public function isTrackerPlugin()
    {
        return true;
    }

    public function install()
    {
        (new LogForm())->install();
    }

    public function uninstall()
    {
        (new LogForm())->uninstall();
    }

    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = 'plugins/RebelMyForms/stylesheets/menuIcon.less';
        $stylesheets[] = 'plugins/RebelMyForms/stylesheets/reports.less';
    }

    public function addMetricTranslations(&$translations)
    {
        foreach (self::METRICS as $metric => $key) {
            $translations[$metric] = 'RebelMyForms_' . $key;
        }
    }

    public function addMetricDocumentationTranslations(&$translations)
    {
        foreach (self::METRICS as $metric => $key) {
            $translations[$metric] = 'RebelMyForms_' . $key . 'Documentation';
        }
    }

    public function getTablesInstalled(&$tables)
    {
        $tables[] = Common::prefixTable(LogForm::TABLE);
    }
}
