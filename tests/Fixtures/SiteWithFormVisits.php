<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelMyForms\tests\Fixtures;

use Piwik\Tests\Framework\Fixture;

/**
 * A website where three visits saw the form "contact": one submitted it, one gave up in the
 * field "email", one only saw it.
 */
class SiteWithFormVisits extends Fixture
{
    public const DAY = '2024-03-05';

    public function setUp(): void
    {
        if (!self::siteCreated(1)) {
            self::createWebsite('2024-01-01 00:00:00');
        }

        $this->trackForm(1, [
            'rmf_s' => 1, 'rmf_c' => 1, 'rmf_t' => 10000, 'rmf_l' => 'email',
            'rmf_f' => '[["name",2000,1,1,0,0],["email",6000,1,1,0,2],["phone",0,0,0,1,0]]',
        ]);
        $this->trackForm(2, [
            'rmf_s' => 1, 'rmf_t' => 4000, 'rmf_l' => 'email',
            'rmf_f' => '[["name",3000,1,1,0,0],["email",1000,3,3,1,0],["phone",0,0,0,1,0]]',
        ]);
        $this->trackForm(3, []);
    }

    public function tearDown(): void
    {
    }

    private function trackForm(int $visit, array $params): void
    {
        $tracker = self::getTracker(1, self::DAY . ' 10:00:00', true, true);
        $tracker->setTokenAuth(self::getTokenAuth());
        $tracker->setNewVisitorId();
        // Matomo takes requests from one address for one visit.
        $tracker->setIp('10.0.0.' . $visit);
        $tracker->setUrl('http://example.org/contact');
        self::checkResponse($tracker->doTrackPageView('Contact'));

        $tracker->setForceVisitDateTime(self::DAY . ' 10:01:00');
        $params += ['ca' => 1, 'rmf_n' => 'contact', 'rmf_s' => 0, 'rmf_c' => 0, 'rmf_r' => 0, 'rmf_t' => 0, 'rmf_l' => '', 'rmf_f' => '[]'];
        foreach ($params as $key => $value) {
            $tracker->setCustomTrackingParameter($key, $value);
        }
        self::checkResponse($tracker->doTrackPageView('Contact'));
    }
}
