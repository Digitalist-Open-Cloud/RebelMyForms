<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelMyForms\RecordBuilders;

use Piwik\ArchiveProcessor;
use Piwik\ArchiveProcessor\Record;
use Piwik\ArchiveProcessor\RecordBuilder;
use Piwik\Config;
use Piwik\Container\StaticContainer;
use Piwik\DataAccess\LogQueryBuilder;
use Piwik\DataTable;
use Piwik\Plugins\RebelMyForms\Columns\FormOutcome;
use Piwik\Plugins\RebelMyForms\Dao\LogForm;

/**
 * The forms of a period: one row for each form with its fields under it, and the totals.
 */
class Forms extends RecordBuilder
{
    public const RECORD = 'RebelMyForms_forms';
    public const PAGES = 'RebelMyForms_pages';
    public const DEVICES = 'RebelMyForms_devices';
    public const NB_VIEWS = 'RebelMyForms_nb_form_views';
    public const NB_STARTS = 'RebelMyForms_nb_form_starts';
    public const NB_SUBMISSIONS = 'RebelMyForms_nb_form_submissions';
    public const NB_SUCCESSES = 'RebelMyForms_nb_form_successes';

    private const MAX_FIELDS_OF_A_FORM = 100;

    private const FIELD_COLUMNS = [
        'nb_field_interactions' => 0,
        'sum_field_time' => 0,
        'nb_field_changes' => 0,
        'nb_field_refocuses' => 0,
        'nb_field_blank' => 0,
        'nb_field_invalid' => 0,
        'nb_field_dropoffs' => 0,
    ];

    public function __construct()
    {
        $maxRows = (int) Config::getInstance()->General['datatable_archiving_maximum_rows_standard'];

        parent::__construct($maxRows ?: null, self::MAX_FIELDS_OF_A_FORM, 'nb_form_views', ['field_position' => 'min']);
    }

    public function getRecordMetadata(ArchiveProcessor $archiveProcessor): array
    {
        return [
            Record::make(Record::TYPE_BLOB, self::RECORD),
            Record::make(Record::TYPE_BLOB, self::PAGES),
            Record::make(Record::TYPE_BLOB, self::DEVICES),
            Record::make(Record::TYPE_NUMERIC, self::NB_VIEWS),
            Record::make(Record::TYPE_NUMERIC, self::NB_STARTS),
            Record::make(Record::TYPE_NUMERIC, self::NB_SUBMISSIONS),
            Record::make(Record::TYPE_NUMERIC, self::NB_SUCCESSES),
        ];
    }

    protected function aggregate(ArchiveProcessor $archiveProcessor): array
    {
        // These count forms, so the rows are not to be grouped to one for each visit.
        $queryBuilder = StaticContainer::get(LogQueryBuilder::class);
        $grouping = $queryBuilder->getForcedInnerGroupBySubselect();
        $queryBuilder->forceInnerGroupBySubselect(LogQueryBuilder::FORCE_INNER_GROUP_BY_NO_SUBSELECT);

        try {
            return $this->countForms($archiveProcessor);
        } finally {
            $queryBuilder->forceInnerGroupBySubselect($grouping);
        }
    }

    /**
     * @return array<string, DataTable|int>
     */
    private function countForms(ArchiveProcessor $archiveProcessor): array
    {
        $logAggregator = $archiveProcessor->getLogAggregator();
        $log = LogForm::TABLE;
        $where = $logAggregator->getWhereStatement($log, 'server_time');

        $query = $logAggregator->generateQuery(
            "$log.form_name AS label, COUNT(*) AS nb_form_views, SUM($log.started) AS nb_form_starts,
             SUM($log.submitted) AS nb_form_submissions, SUM($log.outcome = " . FormOutcome::SUCCEEDED . ") AS nb_form_successes,
             SUM($log.resubmits > 0) AS nb_form_resubmits,
             SUM($log.time_spent) AS sum_form_time",
            [$log],
            $where,
            'label',
            'nb_form_views DESC'
        );

        $table = new DataTable();
        $totals = [self::NB_VIEWS => 0, self::NB_STARTS => 0, self::NB_SUBMISSIONS => 0, self::NB_SUCCESSES => 0];
        foreach ($logAggregator->getDb()->fetchAll($query['sql'], $query['bind']) as $form) {
            $table->addRowFromSimpleArray(array_map('intval', array_slice($form, 1)) + ['label' => $form['label']]);
            $totals[self::NB_VIEWS] += (int) $form['nb_form_views'];
            $totals[self::NB_STARTS] += (int) $form['nb_form_starts'];
            $totals[self::NB_SUBMISSIONS] += (int) $form['nb_form_submissions'];
            $totals[self::NB_SUCCESSES] += (int) $form['nb_form_successes'];
        }

        $query = $logAggregator->generateQuery(
            "$log.page_url AS label, COUNT(*) AS nb_form_views, SUM($log.started) AS nb_form_starts,
             SUM($log.submitted) AS nb_form_submissions",
            [$log],
            $where,
            'label',
            'nb_form_views DESC'
        );
        $pages = new DataTable();
        foreach ($logAggregator->getDb()->fetchAll($query['sql'], $query['bind']) as $page) {
            $pages->addRowFromSimpleArray(array_map('intval', array_slice($page, 1)) + ['label' => $page['label']]);
        }

        // The device is known from the visit a form belongs to.
        $query = $logAggregator->generateQuery(
            "log_visit.config_device_type AS label, COUNT(*) AS nb_form_views, SUM($log.started) AS nb_form_starts,
             SUM($log.submitted) AS nb_form_submissions",
            [$log, 'log_visit'],
            $where,
            'label',
            'nb_form_views DESC'
        );
        $devices = new DataTable();
        foreach ($logAggregator->getDb()->fetchAll($query['sql'], $query['bind']) as $device) {
            $devices->addRowFromSimpleArray(array_map('intval', array_slice($device, 1)) + ['label' => $device['label'] ?? 'xx']);
        }

        $query = $logAggregator->generateQuery(
            "$log.form_name, $log.submitted, $log.last_field, $log.fields",
            [$log],
            $where . " AND $log.started = 1",
            false,
            false
        );
        $fieldsOfForms = [];
        $cursor = $logAggregator->getDb()->query($query['sql'], $query['bind']);
        while ($form = $cursor->fetch()) {
            $this->addFields($fieldsOfForms[$form['form_name']], $form);
        }

        foreach ($table->getRows() as $row) {
            $fields = $fieldsOfForms[$row->getColumn('label')] ?? [];
            if ($fields) {
                $subtable = new DataTable();
                foreach ($fields as $name => $columns) {
                    $subtable->addRowFromSimpleArray(['label' => $name] + $columns);
                }
                $row->setSubtable($subtable);
            }
        }

        return [self::RECORD => $table, self::PAGES => $pages, self::DEVICES => $devices] + $totals;
    }

    /**
     * Adds the fields of one stored form to the sums of its form.
     *
     * @param array<string, array<string, int>>|null $sums
     * @param array<string, mixed> $form
     */
    private function addFields(?array &$sums, array $form): void
    {
        $sums = $sums ?? [];
        $fields = json_decode((string) $form['fields'], true);

        foreach (is_array($fields) ? $fields : [] as $field) {
            [$name, $time, $changes, $focuses, $blank] = $field;
            $sum = $sums[$name] ?? self::FIELD_COLUMNS;
            $sum['nb_field_interactions'] += (int) ($focuses > 0 || $changes > 0);
            $sum['sum_field_time'] += $time;
            $sum['nb_field_changes'] += $changes;
            $sum['nb_field_refocuses'] += max(0, $focuses - 1);
            // A field that is empty says something when the form was sent with it empty.
            $sum['nb_field_blank'] += (int) ($blank && $form['submitted']);
            $sum['nb_field_invalid'] += $field[5] ?? 0;
            // The earliest place the field had in the form.
            if (!empty($field[6])) {
                $sum['field_position'] = min($sum['field_position'] ?? $field[6], $field[6]);
            }
            $sums[$name] = $sum;
        }

        // The field a visitor was in last, in a form they did not submit.
        if (!$form['submitted'] && isset($sums[$form['last_field']])) {
            $sums[$form['last_field']]['nb_field_dropoffs']++;
        }
    }
}
