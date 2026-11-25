<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelMyForms;

use Piwik\Piwik;
use Piwik\Settings\FieldConfig;
use Piwik\Settings\Setting;

class MeasurableSettings extends \Piwik\Settings\Measurable\MeasurableSettings
{
    /**
     * @var Setting
     */
    public $enabled;

    protected function init()
    {
        $this->enabled = $this->makeSetting('enabled', true, FieldConfig::TYPE_BOOL, function (FieldConfig $field) {
            $field->title = Piwik::translate('RebelMyForms_SettingEnabled');
            $field->inlineHelp = Piwik::translate('RebelMyForms_SettingEnabledHelp');
            $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
        });
    }
}
