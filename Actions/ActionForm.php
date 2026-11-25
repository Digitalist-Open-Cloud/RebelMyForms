<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelMyForms\Actions;

use Piwik\Date;
use Piwik\Plugins\RebelMyForms\Dao\LogForm;
use Piwik\Plugins\RebelMyForms\MeasurableSettings;
use Piwik\Plugins\RebelMyForms\Tracker\FormReport;
use Piwik\Tracker\Action;
use Piwik\Tracker\Request;
use Piwik\Tracker\Visitor;

/**
 * A form a visitor saw or used. It is kept in the table of the plugin and not among the page
 * views and events of the visit.
 */
class ActionForm extends Action
{
    public const TYPE_FORM = 88;

    public const MAX_FORMS_PER_VISIT = 200;

    public function __construct(Request $request)
    {
        parent::__construct(self::TYPE_FORM, $request);
    }

    public static function shouldHandle(Request $request)
    {
        return array_key_exists(FormReport::PARAMETER, $request->getParams());
    }

    protected function getActionsToLookup()
    {
        return [];
    }

    public function record(Visitor $visitor, $idReferrerActionUrl, $idReferrerActionName)
    {
        $params = $this->request->getParams();
        $idSite = (int) $this->request->getIdSite();
        $idVisit = $visitor->getVisitorColumn('idvisit');
        $log = new LogForm();

        if (!(new MeasurableSettings($idSite))->enabled->getValue()) {
            return;
        }

        $succeeded = FormReport::succeededForm($params);
        if ($succeeded !== null) {
            $log->markSucceeded($idVisit, $succeeded);

            return;
        }

        $form = FormReport::fromRequestParams($params);
        if ($form === null || $log->countForVisit($idVisit) >= self::MAX_FORMS_PER_VISIT) {
            return;
        }

        $log->record($form + [
            'idsite' => $idSite,
            'idvisit' => $idVisit,
            'idvisitor' => $visitor->getVisitorColumn('idvisitor'),
            'server_time' => Date::factory((int) $this->request->getCurrentTimestamp())->getDatetime(),
        ]);
    }
}
