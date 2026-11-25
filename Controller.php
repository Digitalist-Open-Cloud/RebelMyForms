<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelMyForms;

use Piwik\Piwik;
use Piwik\Plugin\ControllerAdmin;
use Piwik\Plugins\RebelMyForms\Actions\ActionForm;
use Piwik\Plugins\RebelMyForms\Tracker\FormReport;

class Controller extends ControllerAdmin
{
    // As the tracker of the plugin limits it.
    private const MAX_FORMS_PER_PAGE = 20;

    /**
     * The page that tells what is tracked and how to read the reports, with the limits as they are set.
     */
    public function documentation(): string
    {
        Piwik::checkUserHasSomeViewAccess();

        return $this->renderTemplate('documentation', [
            'maxPerPage' => self::MAX_FORMS_PER_PAGE,
            'maxFields' => FormReport::MAX_FIELDS,
            'maxPerVisit' => ActionForm::MAX_FORMS_PER_VISIT,
        ]);
    }
}
