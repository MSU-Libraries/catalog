<?php
/**
 * Form Test Class
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2018.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace CatalogTest\Form;

use Symfony\Component\Yaml\Yaml;
use VuFind\Config\YamlReader;
use VuFind\Form\Form;

/**
 * Form Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class FormTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    protected $mockTestFormYamlReader = null;

    /**
     * Test defaults with no configuration.
     *
     * @return void
     */
    public function testDefaultsWithoutConfiguration()
    {
        $form = new Form(
            new YamlReader(),
            $this->createMock(\Laminas\View\HelperPluginManager::class)
        );
        $this->assertTrue($form->isEnabled());
        $this->assertTrue($form->useCaptcha());
        $this->assertFalse($form->showOnlyForLoggedUsers());
        $this->assertEquals([], $form->getFormElementConfig());
        $this->assertEquals(
            [['email' => null, 'name' => null]],
            $form->getRecipient()
        );
        $this->assertNull($form->getTitle());
        $this->assertNull($form->getHelp());
        $this->assertEquals('VuFind Feedback', $form->getEmailSubject([]));
        $this->assertEquals(
            'Thank you for your feedback.',
            $form->getSubmitResponse()
        );
        $this->assertEquals([[], 'Email/form.phtml'], $form->formatEmailMessage([]));
        $this->assertEquals(
            'Laminas\InputFilter\InputFilter',
            get_class($form->getInputFilter())
        );
    }

}
