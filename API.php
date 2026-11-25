<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelMyForms;

use DeviceDetector\Parser\Device\AbstractDeviceParser;
use Piwik\Archive;
use Piwik\Common;
use Piwik\DataTable;
use Piwik\Piwik;
use Piwik\Plugins\RebelMyForms\RecordBuilders\Forms;

require_once PIWIK_INCLUDE_PATH . '/plugins/DevicesDetection/functions.php';

/**
 * How the visitors of a website use its forms.
 *
 * @method static \Piwik\Plugins\RebelMyForms\API getInstance()
 */
class API extends \Piwik\Plugin\API
{
    /**
     * The totals of a period: how often a form was seen, started, submitted and said to have gone
     * through, the share of the
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
            ->getDataTableFromNumeric([Forms::NB_VIEWS, Forms::NB_STARTS, Forms::NB_SUBMISSIONS, Forms::NB_SUCCESSES]);
        $table->filter('ReplaceColumnNames', [[
            Forms::NB_VIEWS => 'nb_form_views',
            Forms::NB_STARTS => 'nb_form_starts',
            Forms::NB_SUBMISSIONS => 'nb_form_submissions',
            Forms::NB_SUCCESSES => 'nb_form_successes',
        ]]);

        return $table;
    }

    /**
     * The forms of a period, with the average time before a visitor started and from the first
     * field they used to the last, in seconds.
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
     * The fields of all forms of a period, each named by its form and its own name. A field can be
     * followed over time here.
     *
     * @param int|string $idSite
     * @param string $period
     * @param string $date
     * @param string|false $segment
     * @return DataTable|DataTable\Map
     */
    public function getAllFields($idSite, $period, $date, $segment = false)
    {
        Piwik::checkUserHasViewAccess($idSite);

        return Archive::createDataTableFromArchive(Forms::FIELDS, $idSite, $period, $date, $segment);
    }

    /**
     * The forms of a period by the page they are on.
     *
     * @param int|string $idSite
     * @param string $period
     * @param string $date
     * @param string|false $segment
     * @return DataTable|DataTable\Map
     */
    public function getPages($idSite, $period, $date, $segment = false)
    {
        Piwik::checkUserHasViewAccess($idSite);

        $table = Archive::createDataTableFromArchive(Forms::PAGES, $idSite, $period, $date, $segment);
        $table->filter('ColumnCallbackAddMetadata', ['label', 'url', function ($label) {
            return preg_match('#^https?://#i', (string) $label) ? Common::unsanitizeInputValue($label) : false;
        }]);

        return $table;
    }

    /**
     * The forms of a period by the kind of device of the visitor.
     *
     * @param int|string $idSite
     * @param string $period
     * @param string $date
     * @param string|false $segment
     * @return DataTable|DataTable\Map
     */
    public function getDevices($idSite, $period, $date, $segment = false)
    {
        Piwik::checkUserHasViewAccess($idSite);

        $table = Archive::createDataTableFromArchive(Forms::DEVICES, $idSite, $period, $date, $segment);
        // So that a row can open the visits of its kind of device.
        $table->filter('AddSegmentByLabelMapping', ['deviceType', AbstractDeviceParser::getAvailableDeviceTypeNames()]);
        $table->filter('ColumnCallbackAddMetadata', ['label', 'logo', 'Piwik\\Plugins\\DevicesDetection\\getDeviceTypeLogo']);
        $table->filter('ColumnCallbackReplace', ['label', 'Piwik\\Plugins\\DevicesDetection\\getDeviceTypeLabel']);

        return $table;
    }

    /**
     * The fields of one form, with the place each has in it: how many visitors used each, the average time they had it in focus
     * in seconds, how often it was changed, gone back to, left empty in a submitted form, refused by
     * the browser, and how often it was the last field of a visitor who did not submit.
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
