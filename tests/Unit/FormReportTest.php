<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\RebelMyForms\tests\Unit;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\RebelMyForms\Tracker\FormReport;

/**
 * @group RebelMyForms
 * @group Plugins
 */
class FormReportTest extends TestCase
{
    public function testAStartedFormIsReadWithItsFields()
    {
        $form = FormReport::fromRequestParams([
            'rmf_n' => ' contact ',
            'rmf_s' => '1',
            'rmf_c' => '0',
            'rmf_t' => '4200',
            'rmf_l' => 'email',
            'rmf_f' => '[["name",1200,1,1,0],["email",3000,2,3,1,2,5]]',
            'pv_id' => 'aB12cd',
            'url' => "http://example.org/o'neil/contact?email=a@example.org#form",
        ]);

        $this->assertSame([
            'idpageview' => 'aB12cd',
            'form_name' => 'contact',
            'started' => 1,
            'submitted' => 0,
            'outcome' => 1,
            'resubmits' => 0,
            'time_spent' => 4200,
            'last_field' => 'email',
            'fields' => '[["name",1200,1,1,0,0,0],["email",3000,2,3,1,2,5]]',
            'page_url' => 'http://example.org/o&#039;neil/contact',
        ], $form);
    }

    public function testARequestWithoutAFormNameIsNotAForm()
    {
        $this->assertNull(FormReport::fromRequestParams(['rmf_s' => '1']));
        $this->assertNull(FormReport::fromRequestParams(['rmf_n' => '  ']));
        $this->assertNull(FormReport::fromRequestParams(['rmf_n' => ['contact']]));
    }

    public function testAFormThatWasOnlySeenHasNoFieldsAndNoTime()
    {
        $form = FormReport::fromRequestParams(['rmf_n' => 'contact', 'rmf_t' => '500', 'rmf_l' => 'email', 'rmf_f' => '[["email",1,1,1,0]]']);

        $this->assertSame(0, $form['started']);
        $this->assertSame(0, $form['time_spent']);
        $this->assertSame('', $form['last_field']);
        $this->assertSame('[]', $form['fields']);
    }

    public function testASubmittedFormWasStarted()
    {
        $form = FormReport::fromRequestParams(['rmf_n' => 'contact', 'rmf_c' => '1', 'rmf_r' => '2']);

        $this->assertSame(1, $form['started']);
        $this->assertSame(1, $form['submitted']);
        $this->assertSame(2, $form['resubmits']);
        $this->assertSame(2, $form['outcome']);
        $this->assertSame(0, FormReport::fromRequestParams(['rmf_n' => 'contact', 'rmf_s' => '1', 'rmf_r' => '2'])['resubmits']);
    }

    public function testARequestCanSayThatAFormWentThrough()
    {
        $this->assertSame('contact', FormReport::succeededForm(['rmf_n' => ' contact ', 'rmf_ok' => '1']));
        $this->assertNull(FormReport::succeededForm(['rmf_n' => 'contact']));
        $this->assertNull(FormReport::succeededForm(['rmf_n' => 'contact', 'rmf_ok' => 'yes']));
        $this->assertNull(FormReport::succeededForm(['rmf_ok' => '1']));
    }

    public function testWhatCanNotBeReadIsLeftOut()
    {
        $fields = json_encode([
            ['email', -5, 'many', 99999999, 'yes'],
            'not a field',
            [['nested'], 1, 1, 1, 0],
            ['email', 1, 1, 1, 0],
            ['phone'],
        ]);
        $form = FormReport::fromRequestParams(['rmf_n' => 'contact', 'rmf_s' => 1, 'rmf_t' => 'soon', 'rmf_f' => $fields, 'pv_id' => "x' OR 1"]);

        $this->assertSame('', $form['idpageview']);
        $this->assertSame(0, $form['time_spent']);
        $this->assertSame('[["email",0,0,1000,0,0,0],["phone",0,0,0,0,0,0]]', $form['fields']);
        $this->assertSame('[]', FormReport::fromRequestParams(['rmf_n' => 'contact', 'rmf_s' => 1, 'rmf_f' => '{broken'])['fields']);
    }

    public function testNamesAreCutAndEncoded()
    {
        $form = FormReport::fromRequestParams([
            'rmf_n' => '<b>' . str_repeat('n', 200),
            'rmf_s' => 1,
            'rmf_l' => str_repeat('l', 200),
            'rmf_f' => json_encode([['"' . str_repeat('f', 200), 1, 1, 1, 0]]),
        ]);

        $this->assertSame(100, strlen($form['form_name']));
        $this->assertStringStartsWith('&lt;b&gt;nnn', $form['form_name']);
        $this->assertSame(str_repeat('l', 60), $form['last_field']);
        $this->assertSame('&quot;' . str_repeat('f', 59), json_decode($form['fields'])[0][0]);
    }

    public function testNoMoreFieldsThanTheLimitAreKept()
    {
        $fields = [];
        for ($i = 0; $i < FormReport::MAX_FIELDS + 10; $i++) {
            $fields[] = ['field' . $i, 1, 1, 1, 0];
        }
        $form = FormReport::fromRequestParams(['rmf_n' => 'contact', 'rmf_s' => 1, 'rmf_f' => json_encode($fields)]);

        $this->assertCount(FormReport::MAX_FIELDS, json_decode($form['fields']));
    }
}
