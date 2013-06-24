<?php
namespace Magomogo\Persisted\Container;

use Test\DbFixture;
use Test\ObjectMother;
use Magomogo\Persisted\Container\Db;
use Test\CreditCard;
use Test\Company\Model as Company;
use Test\Employee\Model as Employee;
use Test\Person\Model as Person;
use Test\JobRecord\Model as JobRecord;
use Test\Person\Properties as PersonProperties;
use Test\JobRecord\Properties as JobRecordProperties;

class SqliteDbTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var DbFixture
     */
    private $fixture;

    protected function setUp()
    {
        $this->fixture = new DbFixture();
        $this->fixture->install();
    }

    public function testFixtureHasCorrectTablesCreated()
    {
        $this->assertEquals(array(), $this->fixture->db->fetchAll("SELECT * FROM company_properties"));
        $this->assertEquals(array(), $this->fixture->db->fetchAll("SELECT * FROM person_properties"));
        $this->assertEquals(array(), $this->fixture->db->fetchAll("SELECT * FROM creditcard_properties"));
    }

    public function testSavesCreditCardIntoDatabase()
    {
        ObjectMother\CreditCard::datatransTesting()->propertiesFor($this->sqliteContainer())
            ->putIn($this->sqliteContainer());

        $this->assertEquals(
            array(
                'id' => '1',
                'system' => 'VISA',
                'pan' => '9500000000000001',
                'validMonth' => '12',
                'validYear' => '2015',
                'ccv' => '234',
                'cardholderName' => 'Maxim Gnatenko'
            ),
            $this->fixture->db->fetchAssoc("SELECT * FROM creditcard_properties")
        );
    }

    public function testSavesAPersonHavingCreditCardIntoDatabase()
    {
        ObjectMother\Person::maxim()->propertiesFor($this->sqliteContainer())->putIn($this->sqliteContainer());

        $this->assertEquals(
            array(
                'id' => '1',
                'title' => 'Mr.',
                'firstName' => 'Maxim',
                'lastName' => 'Gnatenko',
                'email' => 'maxim@xiag.ch',
                'phone' => '+7923-117-2801',
                'creditCard' => '1',
                'birthDay' => '1975-07-07T00:00:00+07:00'
            ),
            $this->fixture->db->fetchAssoc("SELECT * FROM person_properties")
        );

        $this->assertEquals(
            array(
                'id' => '1',
                'system' => 'VISA',
                'pan' => '9500000000000001',
                'validMonth' => '12',
                'validYear' => '2015',
                'ccv' => '234',
                'cardholderName' => 'Maxim Gnatenko'
            ),
            $this->fixture->db->fetchAssoc("SELECT * FROM creditcard_properties")
        );
    }

    public function testReadsModelFromTheDatabase()
    {
        $maximId = ObjectMother\Person::maxim()->propertiesFor($this->sqliteContainer())->putIn($this->sqliteContainer());
        $this->assertEquals(
            ObjectMother\Person::maxim($maximId)->politeTitle(),
            Person::newProperties($maximId)->loadFrom($this->sqliteContainer())->constructModel()->politeTitle()
        );
    }

    public function testCanUpdateModelInTheDatabase()
    {
        $maximId = ObjectMother\Person::maxim()->propertiesFor($this->sqliteContainer())->putIn($this->sqliteContainer());
        $maxim = Person::newProperties($maximId)->loadFrom($this->sqliteContainer())->constructModel();

        $maxim->phoneNumberIsChanged('903-903');
        $maxim->propertiesFor($this->sqliteContainer())->putIn($this->sqliteContainer());

        $this->assertContains(
            '903-903',
            Person::newProperties($maximId)->loadFrom($this->sqliteContainer())->constructModel()->contactInfo()
        );
    }

    public function testWritesEmployeePropertiesIntoPersonPropertiesTable()
    {
        $this->putEmployeeIn($this->sqliteContainer());

        $this->assertEquals(
            array(
                'id' => '1',
                'title' => 'Mr.',
                'firstName' => 'Maxim',
                'lastName' => 'Gnatenko',
                'email' => 'maxim@xiag.ch',
                'phone' => '+7923-117-2801',
                'creditCard' => '1',
                'company' => '1',
                'birthDay' => '1975-07-07T00:00:00+07:00'
            ),
            $this->fixture->db->fetchAssoc("SELECT * FROM employee_properties")
        );
    }

    public function testReadsEmployeeModel()
    {
        $employee = $this->putEmployeeIn($this->sqliteContainer());
        $this->assertEquals(
            $employee,
            $employee::newProperties(1)->loadFrom($this->sqliteContainer())->constructModel()
        );
    }

    public function testCanSaveAndLoadAJobRecord()
    {
        $currentCompany = ObjectMother\Company::xiag();
        $currentCompany->propertiesFor($this->sqliteContainer())->putIn($this->sqliteContainer());
        $previousCompany = ObjectMother\Company::nstu();
        $previousCompany->propertiesFor($this->sqliteContainer())->putIn($this->sqliteContainer());

        $jobRecordProps = new JobRecordProperties();
        $jobRecordProps->foreign()->currentCompany = $currentCompany->propertiesFor($this->sqliteContainer());
        $jobRecordProps->foreign()->previousCompany = $previousCompany->propertiesFor($this->sqliteContainer());

        $record = new JobRecord(
            $jobRecordProps->foreign()->currentCompany->constructModel(),
            $jobRecordProps->foreign()->previousCompany->constructModel(),
            $jobRecordProps
        );
        $id = $record->propertiesFor($this->sqliteContainer())->putIn($this->sqliteContainer());

        $this->assertEquals(
            $record,
            $record::newProperties($id)->loadFrom(self::sqliteContainer())->constructModel()
        );
    }

    public function testCreatesTwoRecordsOfSameType()
    {
        $this->persistTwoKeymarkers();
        $this->assertEquals('2', $this->fixture->db->fetchColumn('select count(1) from keymarker_properties'));
    }

    private function persistTwoKeymarkers()
    {
        $persistedKeymarker1 = ObjectMother\Keymarker::friend();
        $persistedKeymarker1->propertiesFor($this->sqliteContainer())->putIn($this->sqliteContainer());
        $persistedKeymarker2 = ObjectMother\Keymarker::IT();
        $persistedKeymarker2->propertiesFor($this->sqliteContainer())->putIn($this->sqliteContainer());
        return array($persistedKeymarker1, $persistedKeymarker2);
    }

    public function testStoresPersonKeymarkers()
    {
        list ($persistedKeymarker1, $persistedKeymarker2) = $this->persistTwoKeymarkers();

        $person = ObjectMother\Person::maxim();
        $person->tag($persistedKeymarker1);
        $person->tag($persistedKeymarker2);

        $id = $person->propertiesFor($this->sqliteContainer())->putIn($this->sqliteContainer());

        $this->assertEquals(
            $person,
            $person::newProperties($id)->loadFrom($this->sqliteContainer())->constructModel()
        );
    }

    public function testWorksWithNulls()
    {
        $personProperties = new PersonProperties();
        $personProperties->firstName = 'Vova';
        $personProperties->lastName = null;

        $vova = new Person($personProperties);
        $id = $vova->propertiesFor($this->sqliteContainer())->putIn(self::sqliteContainer());

        $this->assertNull(
            $this->fixture->db->fetchColumn('SELECT lastName FROM person_properties WHERE id = ?', array($id))
        );

        $properties = self::sqliteContainer()->loadProperties(new PersonProperties($id));
        $this->assertNull($properties->lastName);
    }

    public function testACompanyCanBeDeletedFromContainer()
    {
        $company = ObjectMother\Company::xiag();
        $companyId = $company->propertiesFor($this->sqliteContainer())->putIn($this->sqliteContainer());

        $company->propertiesFor($this->sqliteContainer())->deleteFrom($this->sqliteContainer());

        $this->setExpectedException('Magomogo\\Persisted\\Exception\\NotFound');
        $company->propertiesFor($this->sqliteContainer())->loadFrom($this->sqliteContainer(), $companyId);
    }

//----------------------------------------------------------------------------------------------------------------------

    private function putEmployeeIn($container)
    {
        $properties = ObjectMother\Employee::maximProperties();
        $company = new Company($properties->foreign()->company);
        $company->propertiesFor($this->sqliteContainer())->putIn($container);

        $employee = new Employee($company, $properties);
        $employee->propertiesFor($this->sqliteContainer())->putIn($container);
        return $employee;
    }

    private function sqliteContainer()
    {
        return new Db($this->fixture->db, 'Test\\');
    }
}
