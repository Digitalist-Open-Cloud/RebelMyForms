<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelMyForms\Columns;

use Piwik\Columns\Dimension;
use Piwik\Plugins\RebelMyForms\Categories\FormsCategory;
use Piwik\Plugins\RebelMyForms\Dao\LogForm;

/**
 * How far a visitor got with a form: saw it, started and left it, submitted it, or submitted it
 * and the website said it went through.
 */
class FormOutcome extends Dimension
{
    public const SEEN = 0;
    public const ABANDONED = 1;
    public const SUBMITTED = 2;
    public const SUCCEEDED = 3;

    private const WORDS = ['seen' => self::SEEN, 'abandoned' => self::ABANDONED, 'submitted' => self::SUBMITTED, 'succeeded' => self::SUCCEEDED];

    protected $type = self::TYPE_ENUM;
    protected $dbTableName = LogForm::TABLE;
    protected $columnName = 'outcome';
    protected $segmentName = 'formOutcome';
    protected $nameSingular = 'RebelMyForms_DimensionOutcome';
    protected $namePlural = 'RebelMyForms_DimensionOutcomes';
    protected $category = FormsCategory::ID;
    protected $acceptValues = 'seen, abandoned, submitted, succeeded';
    protected $sqlFilterValue = [self::class, 'toNumber'];

    /**
     * The stored number of a word of the segment. A word that is none of them matches nothing.
     *
     * @param mixed $word
     */
    public static function toNumber($word): int
    {
        return self::WORDS[strtolower(trim((string) $word))] ?? -1;
    }
}
