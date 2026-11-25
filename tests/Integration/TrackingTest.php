<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelMyForms\tests\Integration;

use Piwik\API\Request;
use Piwik\Common;
use Piwik\DataTable;
use Piwik\Db;
use Piwik\NoAccessException;
use Piwik\Plugins\API\API as ApiApi;
use Piwik\Plugins\Live\API as LiveApi;
use Piwik\Plugins\PrivacyManager\Model\DataSubjects;
use Piwik\Plugins\RebelMyForms\Actions\ActionForm;
use Piwik\Plugins\RebelMyForms\API as FormsApi;
use Piwik\Plugins\RebelMyForms\Dao\LogForm;
use Piwik\Plugins\RebelMyForms\MeasurableSettings;
use Piwik\Plugins\VisitsSummary\API as VisitsSummaryApi;
use Piwik\Container\StaticContainer;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\Mock\FakeAccess;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * @group RebelMyForms
 * @group Plugins
 */
class TrackingTest extends IntegrationTestCase
{
    private const DAY = '2024-03-05';

    /**
     * @var int
     */
    private $visits = 0;

    public function setUp(): void
    {
        parent::setUp();

        FakeAccess::clearAccess(true);
        Fixture::loadAllTranslations();
        Fixture::createSuperUser();
        Fixture::createWebsite('2024-01-01 00:00:00');
        Fixture::createWebsite('2024-01-01 00:00:00');
        $this->visits = 0;
    }

    public function testAFormIsStoredWithTheVisitItWasSeenIn()
    {
        $this->trackForm($this->startVisit(1), 'contact');

        $forms = $this->getLog();
        $this->assertCount(1, $forms);
        $this->assertSame('contact', $forms[0]['form_name']);
        $this->assertEquals(0, $forms[0]['started']);
        $this->assertEquals(1, $forms[0]['idsite']);
        $this->assertSame(self::DAY . ' 10:01:00', $forms[0]['server_time']);
        $this->assertEquals(Db::fetchOne('SELECT idvisit FROM ' . Common::prefixTable('log_visit')), $forms[0]['idvisit']);
    }

    public function testAFormIsNotCountedAsAPageView()
    {
        $this->trackForm($this->startVisit(1), 'contact');

        $summary = VisitsSummaryApi::getInstance()->get(1, 'day', self::DAY)->getFirstRow();
        $this->assertEquals(1, $summary->getColumn('nb_visits'));
        $this->assertEquals(1, $summary->getColumn('nb_actions'));
    }

    public function testWhatIsSentLaterInAPageViewUpdatesItsForm()
    {
        $tracker = $this->startVisit(1);
        $this->trackForm($tracker, 'contact');
        $this->trackForm($tracker, 'contact', ['rmf_s' => 1, 'rmf_t' => 2000, 'rmf_l' => 'name', 'rmf_f' => '[["name",2000,1,1,0]]']);
        $this->trackForm($tracker, 'contact', ['rmf_s' => 1, 'rmf_c' => 1, 'rmf_t' => 5000, 'rmf_l' => 'email', 'rmf_f' => '[["name",2000,1,1,0],["email",3000,1,1,0]]']);
        // A request that arrives late does not take anything back.
        $this->trackForm($tracker, 'contact');

        $forms = $this->getLog();
        $this->assertCount(1, $forms);
        $this->assertEquals(1, $forms[0]['started']);
        $this->assertEquals(1, $forms[0]['submitted']);
        $this->assertEquals(5000, $forms[0]['time_spent']);
        $this->assertSame('[["name",2000,1,1,0,0],["email",3000,1,1,0,0]]', $forms[0]['fields']);
        $this->assertSame('http://example.org/contact', $forms[0]['page_url']);
    }

    public function testAFormCountsAgainInAnotherPageView()
    {
        $tracker = $this->startVisit(1);
        $this->trackForm($tracker, 'contact');
        $tracker->setPageviewId('second');
        $this->trackForm($tracker, 'contact');

        $this->assertCount(2, $this->getLog());
    }

    public function testAVisitStoresNoMoreFormsThanTheLimit()
    {
        $tracker = $this->startVisit(1);
        $this->trackForm($tracker, 'contact');
        $idVisit = $this->getLog()[0]['idvisit'];
        for ($i = 1; $i < ActionForm::MAX_FORMS_PER_VISIT; $i++) {
            (new LogForm())->record([
                'idsite' => 1, 'idvisit' => $idVisit, 'idvisitor' => 'visitor1', 'server_time' => self::DAY . ' 10:01:00',
                'idpageview' => 'first', 'form_name' => 'form' . $i, 'fields' => '[]',
            ]);
        }

        $this->trackForm($tracker, 'one too many');

        $this->assertCount(ActionForm::MAX_FORMS_PER_VISIT, $this->getLog());
    }

    public function testFormsAreReportedWithTheirRates()
    {
        $this->trackFormsOfThreeVisits();

        $forms = $this->report('getForms');
        $this->assertSame(['contact', 'newsletter'], $forms->getColumn('label'));
        $contact = $forms->getRowFromLabel('contact');
        $this->assertEquals(3, $contact->getColumn('nb_form_views'));
        $this->assertEquals(2, $contact->getColumn('nb_form_starts'));
        $this->assertEquals(1, $contact->getColumn('nb_form_submissions'));
        $this->assertEquals(0.6667, $contact->getColumn('form_start_rate'));
        $this->assertEquals(0.5, $contact->getColumn('form_submit_rate'));
        $this->assertEquals(1, $contact->getColumn('nb_form_resubmits'));
        $this->assertFalse($contact->getColumn('sum_form_time'));
        // (10 + 4 seconds) / 2 starts
        $this->assertEquals(7, $contact->getColumn('avg_form_time'));

        $totals = $this->report('get')->getFirstRow();
        $this->assertEquals(4, $totals->getColumn('nb_form_views'));
        $this->assertEquals(2, $totals->getColumn('nb_form_starts'));
        $this->assertEquals(1, $totals->getColumn('nb_form_submissions'));
        $this->assertEquals(0.5, $totals->getColumn('form_start_rate'));
    }

    public function testTheFieldsOfAFormAreReported()
    {
        $this->trackFormsOfThreeVisits();

        $contact = $this->report('getForms')->getRowFromLabel('contact');
        $fields = $this->report('getFields', 'day', ['idSubtable' => $contact->getIdSubDataTable()]);

        $this->assertEqualsCanonicalizing(['name', 'email', 'phone'], $fields->getColumn('label'));

        $name = $fields->getRowFromLabel('name');
        $this->assertEquals(2, $name->getColumn('nb_field_interactions'));
        $this->assertEquals(2.5, $name->getColumn('avg_field_time'));
        $this->assertEquals(0, $name->getColumn('nb_field_dropoffs'));

        $email = $fields->getRowFromLabel('email');
        $this->assertEquals(2, $email->getColumn('nb_field_interactions'));
        $this->assertEquals(4, $email->getColumn('nb_field_changes'));
        $this->assertEquals(2, $email->getColumn('nb_field_refocuses'));
        $this->assertEquals(1, $email->getColumn('nb_field_dropoffs'));
        $this->assertEquals(2, $email->getColumn('nb_field_invalid'));
        $this->assertEquals(0.5, $email->getColumn('field_dropoff_rate'));

        // Nobody used it, and it was empty in the one form that was submitted.
        $phone = $fields->getRowFromLabel('phone');
        $this->assertEquals(0, $phone->getColumn('nb_field_interactions'));
        $this->assertEquals(1, $phone->getColumn('nb_field_blank'));
    }

    public function testFormsAreReportedByThePageTheyAreOn()
    {
        $this->trackFormsOfThreeVisits();
        $tracker = $this->startVisit(1);
        $tracker->setUrl('http://example.org/signup?ref=mail');
        $this->trackForm($tracker, 'signup', ['rmf_s' => 1]);

        $pages = $this->report('getPages');

        $this->assertSame(['http://example.org/contact', 'http://example.org/signup'], $pages->getColumn('label'));
        $this->assertEquals([4, 1], $pages->getColumn('nb_form_views'));
        $this->assertEquals([0.5, 1], $pages->getColumn('form_start_rate'));
        $this->assertSame('http://example.org/signup', $pages->getLastRow()->getMetadata('url'));

        $evolution = ApiApi::getInstance()->getRowEvolution(1, 'day', '2024-03-04,' . self::DAY, 'RebelMyForms', 'getPages', 'http://example.org/signup');
        $this->assertEquals(1, $evolution['reportData']->getTable(self::DAY)->getFirstRow()->getColumn('nb_form_views'));
    }

    public function testTheVisitLogShowsTheFormsAVisitorStarted()
    {
        $this->trackFormsOfThreeVisits();

        $visits = LiveApi::getInstance()->getLastVisitsDetails(1, 'day', self::DAY);
        $visits->applyQueuedFilters();
        $forms = [];
        foreach ($visits->getRows() as $visit) {
            foreach ($visit->getColumn('actionDetails') as $action) {
                if ($action['type'] === 'rebelForm') {
                    $forms[] = $action['formName'] . ': ' . $action['formOutcome'];
                }
            }
        }

        $this->assertEqualsCanonicalizing(['contact: submitted', 'contact: not submitted, left at email'], $forms);
    }

    public function testNothingIsStoredForAWebsiteThatHasFormTrackingTurnedOff()
    {
        $settings = new MeasurableSettings(1);
        $settings->enabled->setValue(false);
        $settings->save();

        $this->trackForm($this->startVisit(1), 'contact');
        $this->trackForm($this->startVisit(2), 'contact');

        $forms = $this->getLog();
        $this->assertCount(1, $forms);
        $this->assertEquals(2, $forms[0]['idsite']);
    }

    public function testFormsAreSummedOverSeveralDays()
    {
        $this->trackFormsOfThreeVisits('2024-03-04');
        $this->trackFormsOfThreeVisits();

        $contact = $this->report('getForms', 'week')->getRowFromLabel('contact');
        $this->assertEquals(6, $contact->getColumn('nb_form_views'));
        $this->assertEquals(7, $contact->getColumn('avg_form_time'));

        $fields = $this->report('getFields', 'week', ['idSubtable' => $contact->getIdSubDataTable()]);
        $this->assertEquals(2, $fields->getRowFromLabel('email')->getColumn('nb_field_dropoffs'));
    }

    public function testTheFormsOfASegmentOfVisitsAreReported()
    {
        $tracker = $this->startVisit(1);
        $tracker->setUserId('alice');
        $this->trackForm($tracker, 'contact', ['rmf_s' => 1, 'rmf_f' => '[["name",1000,1,1,0]]']);
        // Two forms in one visit are still two forms.
        $this->trackForm($tracker, 'newsletter');
        $this->trackForm($this->startVisit(1), 'contact');

        $forms = FormsApi::getInstance()->getForms(1, 'day', self::DAY, 'userId==alice');
        $this->assertEqualsCanonicalizing(['contact', 'newsletter'], $forms->getColumn('label'));
        $this->assertEquals(1, $forms->getRowFromLabel('contact')->getColumn('nb_form_views'));
        $this->assertEquals(2, FormsApi::getInstance()->get(1, 'day', self::DAY, 'userId==alice')->getFirstRow()->getColumn('nb_form_views'));
        $this->assertEquals(2, FormsApi::getInstance()->getForms(1, 'day', self::DAY)->getRowFromLabel('contact')->getColumn('nb_form_views'));
    }

    public function testAFormCanBeFollowedOverTime()
    {
        $this->trackFormsOfThreeVisits('2024-03-04');
        $this->trackFormsOfThreeVisits();

        $evolution = ApiApi::getInstance()->getRowEvolution(1, 'day', '2024-03-04,' . self::DAY, 'RebelMyForms', 'getForms', 'contact');

        $this->assertEquals(3, $evolution['reportData']->getTable(self::DAY)->getFirstRow()->getColumn('nb_form_views'));
    }

    public function testOtherReportsCanBeNarrowedToVisitsThatSawAForm()
    {
        $this->trackFormsOfThreeVisits();

        $summary = VisitsSummaryApi::getInstance()->get(1, 'day', self::DAY, 'formName==newsletter')->getFirstRow();

        $this->assertEquals(1, $summary->getColumn('nb_visits'));
    }

    public function testFormsAreDeletedWithTheirVisit()
    {
        $this->trackForm($this->startVisit(1), 'contact');
        $this->trackForm($this->startVisit(1), 'contact');
        $idVisit = $this->getLog()[0]['idvisit'];

        StaticContainer::get(DataSubjects::class)->deleteDataSubjects([['idsite' => 1, 'idvisit' => $idVisit]]);

        $forms = $this->getLog();
        $this->assertCount(1, $forms);
        $this->assertNotEquals($idVisit, $forms[0]['idvisit']);
    }

    /**
     * @dataProvider getReports
     */
    public function testAReportNeedsViewAccessToItsWebsite(string $method, array $extra)
    {
        $this->trackForm($this->startVisit(2), 'contact');
        FakeAccess::clearAccess(false, [], [1]);

        $this->assertNotNull(FormsApi::getInstance()->$method(1, 'day', self::DAY, ...$extra));

        $this->expectException(NoAccessException::class);
        FormsApi::getInstance()->$method(2, 'day', self::DAY, ...$extra);
    }

    public function getReports(): array
    {
        return [['get', []], ['getForms', []], ['getPages', []], ['getFields', [1]]];
    }

    /**
     * Three visits to a page with the form "contact". One submits it, one gives up in the field
     * "email", one only sees it. The first also sees the form "newsletter".
     */
    private function trackFormsOfThreeVisits(string $day = self::DAY): void
    {
        $submits = $this->startVisit(1, $day);
        $this->trackForm($submits, 'contact', [
            'rmf_s' => 1, 'rmf_c' => 1, 'rmf_r' => 1, 'rmf_t' => 10000, 'rmf_l' => 'email',
            'rmf_f' => '[["name",2000,1,1,0],["email",6000,1,1,0,2],["phone",0,0,0,1]]',
        ]);
        $this->trackForm($submits, 'newsletter');

        $this->trackForm($this->startVisit(1, $day), 'contact', [
            'rmf_s' => 1, 'rmf_t' => 4000, 'rmf_l' => 'email',
            'rmf_f' => '[["name",3000,1,1,0],["email",1000,3,3,1],["phone",0,0,0,1]]',
        ]);

        $this->trackForm($this->startVisit(1, $day), 'contact');
    }

    /**
     * A report as the API gives it, with the metrics that are worked out from others.
     */
    private function report(string $method, string $period = 'day', array $extra = []): DataTable
    {
        return Request::processRequest('RebelMyForms.' . $method, ['idSite' => 1, 'period' => $period, 'date' => self::DAY, 'format_metrics' => 0] + $extra);
    }

    private function startVisit(int $idSite, string $day = self::DAY): \MatomoTracker
    {
        $tracker = Fixture::getTracker($idSite, $day . ' 10:00:00', true, true);
        $tracker->setTokenAuth(Fixture::getTokenAuth());
        $tracker->setNewVisitorId();
        // Matomo takes requests from one address for one visit.
        $tracker->setIp('10.0.0.' . (1 + $this->visits++));
        $tracker->setUrl('http://example.org/contact');
        $tracker->setPageviewId('first');
        Fixture::checkResponse($tracker->doTrackPageView('Contact'));
        $tracker->setForceVisitDateTime($day . ' 10:01:00');

        return $tracker;
    }

    private function trackForm(\MatomoTracker $tracker, string $name, array $params = []): void
    {
        $params += ['ca' => 1, 'rmf_n' => $name, 'rmf_s' => 0, 'rmf_c' => 0, 'rmf_r' => 0, 'rmf_t' => 0, 'rmf_l' => '', 'rmf_f' => '[]'];
        foreach ($params as $key => $value) {
            $tracker->setCustomTrackingParameter($key, $value);
        }
        Fixture::checkResponse($tracker->doTrackPageView('Contact'));
    }

    private function getLog(): array
    {
        return Db::fetchAll('SELECT * FROM ' . Common::prefixTable(LogForm::TABLE) . ' ORDER BY idlog');
    }

    public function provideContainerConfig()
    {
        return ['Piwik\Access' => new FakeAccess()];
    }

    protected static function configureFixture($fixture)
    {
        parent::configureFixture($fixture);
        $fixture->createSuperUser = true;
    }
}
