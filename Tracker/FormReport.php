<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelMyForms\Tracker;

use Piwik\Common;

/**
 * What a browser sent about a form. Nothing in it can be trusted.
 */
class FormReport
{
    public const PARAMETER = 'rmf_n';

    // Sent with the name of a form the website says went through.
    public const SUCCESS_PARAMETER = 'rmf_ok';

    public const MAX_FIELDS = 50;

    private const MAX_FORM_NAME = 100;
    private const MAX_FIELD_NAME = 60;
    private const MAX_ADDRESS = 500;
    private const MAX_FIELDS_JSON = 20000;
    private const MAX_TIME_MS = 4 * 3600 * 1000;
    private const MAX_COUNT = 1000;

    /**
     * The columns to store, or null when the request does not hold a usable form. The names are
     * encoded for HTML, as Matomo stores what the tracker receives.
     *
     * @param array<string, mixed> $params
     * @return array<string, int|string>|null
     */
    public static function fromRequestParams(array $params): ?array
    {
        $name = self::text($params[self::PARAMETER] ?? '', self::MAX_FORM_NAME);
        if ($name === '') {
            return null;
        }

        $submitted = (int) (($params['rmf_c'] ?? 0) == 1);
        $started = (int) ($submitted || ($params['rmf_s'] ?? 0) == 1);
        $idPageView = $params['pv_id'] ?? '';
        $url = $params['url'] ?? '';

        return [
            'idpageview' => is_string($idPageView) && preg_match('/^[a-zA-Z0-9]{1,6}$/', $idPageView) ? $idPageView : '',
            'form_name' => $name,
            'started' => $started,
            'submitted' => $submitted,
            // As the numbers of Columns\FormOutcome.
            'outcome' => $started + $submitted,
            'resubmits' => $submitted ? self::number($params['rmf_r'] ?? 0, self::MAX_COUNT) : 0,
            'time_to_start' => $started ? self::number($params['rmf_w'] ?? 0, self::MAX_TIME_MS) : 0,
            'time_spent' => $started ? self::number($params['rmf_t'] ?? 0, self::MAX_TIME_MS) : 0,
            'last_field' => $started ? self::text($params['rmf_l'] ?? '', self::MAX_FIELD_NAME) : '',
            'fields' => json_encode($started ? self::fields($params['rmf_f'] ?? '') : []),
            // What follows ? and # can hold personal data and is not needed to find the form.
            'page_url' => self::text(is_string($url) ? preg_split('/[?#]/', $url)[0] : '', self::MAX_ADDRESS, self::MAX_ADDRESS),
        ];
    }

    /**
     * The name of the form a request says went through, or null when it does not say so.
     *
     * @param array<string, mixed> $params
     */
    public static function succeededForm(array $params): ?string
    {
        $name = self::text($params[self::PARAMETER] ?? '', self::MAX_FORM_NAME);

        return $name !== '' && ($params[self::SUCCESS_PARAMETER] ?? 0) == 1 ? $name : null;
    }

    /**
     * The fields as [name, milliseconds with focus, changes, times focused, 1 when left blank,
     * times the browser refused it, place in the form, milliseconds before the first answer].
     *
     * @param mixed $json
     * @return array<int, array{string, int, int, int, int, int, int, int}>
     */
    private static function fields($json): array
    {
        $list = is_string($json) && strlen($json) <= self::MAX_FIELDS_JSON ? json_decode($json, true) : null;
        if (!is_array($list)) {
            return [];
        }

        $fields = [];
        foreach ($list as $item) {
            $name = is_array($item) ? self::text($item[0] ?? '', self::MAX_FIELD_NAME) : '';
            if ($name === '' || isset($fields[$name])) {
                continue;
            }
            $fields[$name] = [
                $name,
                self::number($item[1] ?? 0, self::MAX_TIME_MS),
                self::number($item[2] ?? 0, self::MAX_COUNT),
                self::number($item[3] ?? 0, self::MAX_COUNT),
                (int) (($item[4] ?? 0) == 1),
                self::number($item[5] ?? 0, self::MAX_COUNT),
                self::number($item[6] ?? 0, self::MAX_COUNT),
                self::number($item[7] ?? 0, self::MAX_TIME_MS),
            ];
            if (count($fields) >= self::MAX_FIELDS) {
                break;
            }
        }

        return array_values($fields);
    }

    /**
     * @param mixed $value
     */
    private static function text($value, int $max, int $maxEncoded = 100): string
    {
        if (!is_string($value)) {
            return '';
        }

        $text = trim(mb_substr(mb_scrub($value, 'UTF-8'), 0, $max, 'UTF-8'));

        // Encoding makes a text longer than its column.
        return mb_substr(Common::sanitizeInputValue($text), 0, $maxEncoded, 'UTF-8');
    }

    /**
     * @param mixed $value
     */
    private static function number($value, int $max): int
    {
        return is_numeric($value) ? max(0, min($max, (int) $value)) : 0;
    }
}
