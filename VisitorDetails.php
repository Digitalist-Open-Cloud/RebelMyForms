<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelMyForms;

use Piwik\Piwik;
use Piwik\Plugins\Live\VisitorDetailsAbstract;
use Piwik\Plugins\RebelMyForms\Columns\FormOutcome;
use Piwik\Plugins\RebelMyForms\Dao\LogForm;
use Piwik\View;

/**
 * Shows the forms a visitor started among the page views and events of the visit in the visit log.
 */
class VisitorDetails extends VisitorDetailsAbstract
{
    // Not "form": another plugin with forms in the visit log would take the action for its own.
    public const ACTION_TYPE = 'rebelForm';

    public function provideActionsForVisitIds(&$actions, $visitIds)
    {
        foreach ((new LogForm())->getStartedFormsOfVisits($visitIds) as $form) {
            if ($form['outcome'] == FormOutcome::SUCCEEDED) {
                $outcome = Piwik::translate('RebelMyForms_VisitLogSucceeded');
            } elseif ($form['outcome'] == FormOutcome::SUBMITTED) {
                $outcome = Piwik::translate('RebelMyForms_VisitLogSubmitted');
            } elseif ($form['last_field'] !== '') {
                $outcome = Piwik::translate('RebelMyForms_VisitLogLeftAt', [$form['last_field']]);
            } else {
                $outcome = Piwik::translate('RebelMyForms_VisitLogNotSubmitted');
            }

            $actions[$form['idvisit']][] = [
                'type' => self::ACTION_TYPE,
                'serverTimePretty' => $form['server_time'],
                'title' => Piwik::translate('RebelMyForms_DimensionForm'),
                'subtitle' => $form['form_name'],
                'formName' => $form['form_name'],
                'formOutcome' => $outcome,
            ];
        }
    }

    public function renderAction($action, $previousAction, $visitorDetails)
    {
        if (($action['type'] ?? '') !== self::ACTION_TYPE) {
            return '';
        }

        $view = new View('@RebelMyForms/_actionForm.twig');
        $view->sendHeadersWhenRendering = false;
        $view->action = $action;

        return $view->render();
    }
}
