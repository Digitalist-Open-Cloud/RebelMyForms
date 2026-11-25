<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelMyForms\Tracker;

use Piwik\Plugins\RebelMyForms\Dao\LogForm;
use Piwik\Tracker\LogTable;

class FormLogTable extends LogTable
{
    public function getName()
    {
        return LogForm::TABLE;
    }

    public function getIdColumn()
    {
        return 'idlog';
    }

    public function getPrimaryKey()
    {
        return ['idlog'];
    }

    public function getColumnToJoinOnIdVisit()
    {
        return 'idvisit';
    }

    public function getDateTimeColumn()
    {
        return 'server_time';
    }

    public function hasIdVisitorColumn(): bool
    {
        return true;
    }

    // A visit can have several forms, so a report about visits that is narrowed by a form counts
    // the visit once.
    public function shouldJoinWithSubSelect()
    {
        return true;
    }
}
