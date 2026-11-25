<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelMyForms\Columns;

use Piwik\Columns\Dimension;
use Piwik\Common;
use Piwik\Plugins\RebelMyForms\Categories\FormsCategory;
use Piwik\Plugins\RebelMyForms\Dao\LogForm;

class FormName extends Dimension
{
    protected $type = self::TYPE_TEXT;
    protected $dbTableName = LogForm::TABLE;
    protected $columnName = 'form_name';
    protected $segmentName = 'formName';
    protected $nameSingular = 'RebelMyForms_DimensionForm';
    protected $namePlural = 'RebelMyForms_Forms';
    protected $category = FormsCategory::ID;

    // The names are stored encoded for HTML, so a value to compare with is encoded the same way.
    protected $sqlFilterValue = [Common::class, 'sanitizeInputValue'];

    public function __construct()
    {
        // Matomo only knows the page views and events of a visit, not its forms.
        $this->suggestedValuesCallback = function ($idSite, $maxValuesToReturn) {
            return (new LogForm())->getMostSeenFormNames((int) $idSite, (int) $maxValuesToReturn);
        };
    }
}
