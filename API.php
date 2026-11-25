<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelMyForms;

use Piwik\Archive;
use Piwik\DataTable;
use Piwik\Piwik;
use Piwik\Plugins\RebelMyForms\RecordBuilders\Forms;

/**
 * How the visitors of a website use its forms.
 *
 * @method static \Piwik\Plugins\RebelMyForms\API getInstance()
 */
class API extends \Piwik\Plugin\API
{
    /**
     * The totals of a period: how often a form was seen, started and submitted, the share of the
     * seen forms that were started, and the share of the started forms that were submitted.
     *
     * @param int|string $idSite
     * @param string $period
     * @param string $date
     * @param string|false $segment
     * @return DataTable|DataTable\Map
     */
    public function get($idSite, $period, $date, $segment = false)
    {
        Piwik::checkUserHasViewAccess($idSite);

        $table = Archive::build($idSite, $period, $date, $segment)
            ->getDataTableFromNumeric([Forms::NB_VIEWS, Forms::NB_STARTS, Forms::NB_SUBMISSIONS]);
        $table->filter('ReplaceColumnNames', [[
            Forms::NB_VIEWS => 'nb_form_views',
            Forms::NB_STARTS => 'nb_form_starts',
            Forms::NB_SUBMISSIONS => 'nb_form_submissions',
        ]]);

        return $table;
    }

    /**
     * The forms of a period, with the average time from the first field a visitor used to the
     * last, in seconds.
     *
     * @param int|string $idSite
     * @param string $period
     * @param string $date
     * @param string|false $segment
     * @return DataTable|DataTable\Map
     */
    public function getForms($idSite, $period, $date, $segment = false)
    {
        Piwik::checkUserHasViewAccess($idSite);

        return Archive::createDataTableFromArchive(Forms::RECORD, $idSite, $period, $date, $segment);
    }

    /**
     * The fields of one form: how many visitors used each, the average time they had it in focus
     * in seconds, how often it was changed, gone back to, left empty in a submitted form, and how
     * often it was the last field of a visitor who did not submit.
     *
     * @param int|string $idSite
     * @param string $period
     * @param string $date
     * @param int $idSubtable The ID of the row in `getForms`.
     * @param string|false $segment
     * @return DataTable|DataTable\Map
     */
    public function getFields($idSite, $period, $date, $idSubtable, $segment = false)
    {
        Piwik::checkUserHasViewAccess($idSite);

        return Archive::createDataTableFromArchive(Forms::RECORD, $idSite, $period, $date, $segment, false, false, (int) $idSubtable);
    }
}
