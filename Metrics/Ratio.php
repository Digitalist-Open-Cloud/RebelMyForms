<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelMyForms\Metrics;

use Piwik\Columns\Dimension;
use Piwik\DataTable\Row;
use Piwik\Metrics\Formatter;
use Piwik\Piwik;
use Piwik\Plugin\ProcessedMetric;
use Piwik\Plugins\RebelMyForms\RebelMyForms;

/**
 * One column of a row divided by another: a share, or an average time in seconds from a sum of
 * milliseconds.
 */
class Ratio extends ProcessedMetric
{
    private string $name;
    private string $numerator;
    private string $denominator;
    private bool $isTime;

    public function __construct(string $name, string $numerator, string $denominator, bool $isTime = false)
    {
        $this->name = $name;
        $this->numerator = $numerator;
        $this->denominator = $denominator;
        $this->isTime = $isTime;
    }

    public static function startRate(): self
    {
        return new self('form_start_rate', 'nb_form_starts', 'nb_form_views');
    }

    public static function submitRate(): self
    {
        return new self('form_submit_rate', 'nb_form_submissions', 'nb_form_starts');
    }

    public function getName()
    {
        return $this->name;
    }

    public function getTranslatedName()
    {
        return Piwik::translate('RebelMyForms_' . RebelMyForms::METRICS[$this->name]);
    }

    public function getDependentMetrics()
    {
        return [$this->numerator, $this->denominator];
    }

    public function getTemporaryMetrics()
    {
        // The sum of a time is only there to work out the average.
        return $this->isTime ? [$this->numerator] : [];
    }

    public function compute(Row $row)
    {
        $numerator = (float) self::getMetric($row, $this->numerator);
        $denominator = (float) self::getMetric($row, $this->denominator);

        return $this->isTime
            ? Piwik::getQuotientSafe($numerator / 1000, $denominator, 1)
            : Piwik::getQuotientSafe($numerator, $denominator, 4);
    }

    public function format($value, Formatter $formatter)
    {
        if (!is_numeric($value)) {
            return $value;
        }

        return $this->isTime ? $formatter->getPrettyTimeFromSeconds($value, true) : $formatter->getPrettyPercentFromQuotient($value);
    }

    public function getSemanticType(): ?string
    {
        return $this->isTime ? Dimension::TYPE_DURATION_S : Dimension::TYPE_PERCENT;
    }
}
