<?php
namespace Test;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Connection;

class DbFixture
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    public $db;

    public function __construct()
    {
        $this->db = DriverManager::getConnection(
            array(
                'memory' => true,
                'user' => '',
                'password' => '',
                'driver' => 'pdo_sqlite',
            ),
            new \Doctrine\DBAL\Configuration
        );
    }

    public function install()
    {
        self::installSchema($this->db);
    }

//----------------------------------------------------------------------------------------------------------------------

    private static function installSchema(Connection $db)
    {
        $db->exec(<<<SQL

CREATE table creditcard_model (
  id INTEGER CONSTRAINT pk_creditcard PRIMARY KEY AUTOINCREMENT,
  system TEXT,
  pan TEXT,
  validMonth TEXT,
  validYear TEXT,
  ccv TEXT,
  cardholderName TEXT
);

CREATE table person_model (
  id INTEGER CONSTRAINT pk_person PRIMARY KEY AUTOINCREMENT,
  title TEXT,
  firstName TEXT,
  lastName TEXT,
  email TEXT,
  phone TEXT,
  creditCard INTEGER CONSTRAINT fk_person_to_cc REFERENCES creditcard_model (id) ON DELETE SET NULL ON UPDATE CASCADE
);

SQL
        );
    }
}