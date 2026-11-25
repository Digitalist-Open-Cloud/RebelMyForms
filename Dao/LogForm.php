<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelMyForms\Dao;

use Piwik\Common;
use Piwik\Date;
use Piwik\Db;
use Piwik\DbHelper;
use Piwik\Plugins\RebelMyForms\Columns\FormOutcome;

/**
 * One row for each form a visitor saw in a page view, with what they did in it.
 */
class LogForm
{
    public const TABLE = 'rebelmyforms_log';

    private const DAYS_OF_SUGGESTIONS = 60;

    // ponytail: the fields are JSON on the row of their form and summed in PHP when archiving.
    // A table of their own when a segment on a field is needed.
    public function install(): void
    {
        DbHelper::createTable(self::TABLE, "
            `idlog` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `idsite` INT UNSIGNED NOT NULL,
            `idvisit` BIGINT UNSIGNED NOT NULL,
            `idvisitor` BINARY(8) NOT NULL,
            `server_time` DATETIME NOT NULL,
            `idpageview` CHAR(6) NOT NULL,
            `form_name` VARCHAR(100) NOT NULL,
            `started` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `submitted` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `outcome` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `resubmits` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `time_to_start` INT UNSIGNED NOT NULL DEFAULT 0,
            `time_spent` INT UNSIGNED NOT NULL DEFAULT 0,
            `last_field` VARCHAR(100) NOT NULL DEFAULT '',
            `fields` TEXT NOT NULL,
            `page_url` VARCHAR(500) NOT NULL DEFAULT '',
            PRIMARY KEY (`idlog`),
            UNIQUE KEY `form_of_pageview` (`idvisit`, `idpageview`, `form_name`),
            KEY `site_time` (`idsite`, `server_time`)
        ");
    }

    public function uninstall(): void
    {
        Db::dropTables([$this->table()]);
    }

    /**
     * Stores a form of a page view, or brings the row it has up to date. What was started or
     * submitted stays so.
     *
     * @param array<string, mixed> $row
     */
    public function record(array $row): void
    {
        $columns = array_keys($row);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)
             ON DUPLICATE KEY UPDATE
                last_field = IF(VALUES(started) = 1, VALUES(last_field), last_field),
                fields = IF(VALUES(started) = 1, VALUES(fields), fields),
                started = GREATEST(started, VALUES(started)),
                submitted = GREATEST(submitted, VALUES(submitted)),
                outcome = GREATEST(outcome, VALUES(outcome)),
                resubmits = GREATEST(resubmits, VALUES(resubmits)),
                time_to_start = GREATEST(time_to_start, VALUES(time_to_start)),
                time_spent = GREATEST(time_spent, VALUES(time_spent))',
            $this->table(),
            implode(', ', $columns),
            implode(', ', array_fill(0, count($columns), '?'))
        );
        Db::query($sql, array_values($row));
    }

    /**
     * Marks the form of that name the visit saw last as one that went through.
     *
     * @param int|string $idVisit
     */
    public function markSucceeded($idVisit, string $formName): void
    {
        Db::query(
            'UPDATE ' . $this->table() . ' SET started = 1, submitted = 1, outcome = ' . FormOutcome::SUCCEEDED . '
              WHERE idvisit = ? AND form_name = ? ORDER BY idlog DESC LIMIT 1',
            [$idVisit, $formName]
        );
    }

    /**
     * @param int|string $idVisit
     */
    public function countForVisit($idVisit): int
    {
        return (int) Db::fetchOne('SELECT COUNT(*) FROM ' . $this->table() . ' WHERE idvisit = ?', [$idVisit]);
    }

    /**
     * The names of the forms of a website that were seen most in the last days, as they are stored.
     *
     * @return string[]
     */
    public function getMostSeenFormNames(int $idSite, int $limit): array
    {
        return array_column(Db::fetchAll(
            'SELECT form_name FROM ' . $this->table() . '
              WHERE idsite = ? AND server_time > ?
              GROUP BY form_name ORDER BY COUNT(*) DESC, form_name LIMIT ' . max(1, $limit),
            [$idSite, Date::now()->subDay(self::DAYS_OF_SUGGESTIONS)->getDatetime()]
        ), 'form_name');
    }

    /**
     * The forms that were started in the given visits.
     *
     * @param array<int|string> $idVisits
     * @return array<int, array<string, mixed>>
     */
    public function getStartedFormsOfVisits(array $idVisits): array
    {
        $idVisits = array_map('intval', $idVisits);
        if (!$idVisits) {
            return [];
        }

        return Db::fetchAll(
            'SELECT idvisit, server_time, form_name, outcome, last_field
               FROM ' . $this->table() . '
              WHERE idvisit IN (' . Common::getSqlStringFieldsArray($idVisits) . ') AND started = 1
              ORDER BY idlog',
            $idVisits
        );
    }

    private function table(): string
    {
        return Common::prefixTable(self::TABLE);
    }
}
