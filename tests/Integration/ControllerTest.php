<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelMyForms\tests\Integration;

use Piwik\NoAccessException;
use Piwik\Plugins\RebelMyForms\Controller;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\Mock\FakeAccess;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * @group RebelMyForms
 * @group Plugins
 */
class ControllerTest extends IntegrationTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Fixture::loadAllTranslations();
        Fixture::createWebsite('2024-01-01 00:00:00');
    }

    public function testAUserWithViewAccessReadsTheDocumentationWithTheLimitsAsTheyAreSet()
    {
        FakeAccess::clearAccess(false, [], [1]);

        $page = (new Controller())->documentation();

        $this->assertStringContainsString('What counts as submitted', $page);
        $this->assertStringContainsString('up to 20 forms on a page and 50 fields in a form', $page);
        $this->assertStringContainsString('A visit stores at most 200 forms.', $page);
        $this->assertStringContainsString('data-rebel-form="Newsletter signup"', $page);
        $this->assertStringContainsString("RebelMyForms.success('contact');", $page);
        // No text of the page is left untranslated.
        $this->assertSame(0, preg_match('/RebelMyForms_Doc[A-Z]/', $page));
    }

    public function testAUserWithoutAccessToAnyWebsiteDoesNotGetTheDocumentation()
    {
        FakeAccess::clearAccess(false);

        $this->expectException(NoAccessException::class);
        (new Controller())->documentation();
    }

    public function provideContainerConfig()
    {
        return ['Piwik\Access' => new FakeAccess()];
    }
}
