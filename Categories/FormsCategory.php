<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelMyForms\Categories;

use Piwik\Category\Category;

class FormsCategory extends Category
{
    public const ID = 'RebelMyForms_Forms';

    protected $id = self::ID;
    protected $order = 68;
    protected $icon = 'rebelMyFormsIcon';
}
