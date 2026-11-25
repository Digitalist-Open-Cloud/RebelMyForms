<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelMyForms\Columns;

use Piwik\Columns\Dimension;

/**
 * Names the rows of the report of all fields. It is not a segment.
 */
class FormField extends Dimension
{
    protected $type = self::TYPE_TEXT;
    protected $nameSingular = 'RebelMyForms_ColumnField';
}
