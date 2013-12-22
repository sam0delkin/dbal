<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;

class DB2PlatformTest extends AbstractPlatformTestCase
{
    /**
     * @var \Doctrine\DBAL\Platforms\DB2Platform
     */
    protected $_platform;

    public function createPlatform()
    {
        return new DB2Platform();
    }

    public function getGenerateAlterTableSql()
    {
        return array(
            "ALTER TABLE mytable ADD COLUMN quota INTEGER DEFAULT NULL DROP COLUMN foo ALTER bar baz VARCHAR(255) DEFAULT 'def' NOT NULL ALTER bloo bloo SMALLINT DEFAULT '0' NOT NULL",
            "CALL SYSPROC.ADMIN_CMD ('REORG TABLE mytable')",
            'RENAME TABLE mytable TO userlist',
        );
    }

    public function getGenerateForeignKeySql()
    {
        return 'ALTER TABLE test ADD FOREIGN KEY (fk_name_id) REFERENCES other_table (id)';
    }

    public function getGenerateIndexSql()
    {
        return 'CREATE INDEX my_idx ON mytable (user_name, last_login)';
    }

    public function getGenerateTableSql()
    {
        return 'CREATE TABLE test (id INTEGER GENERATED BY DEFAULT AS IDENTITY NOT NULL, test VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))';
    }

    public function getGenerateTableWithMultiColumnUniqueIndexSql()
    {
        return array(
            'CREATE TABLE test (foo VARCHAR(255) DEFAULT NULL, bar VARCHAR(255) DEFAULT NULL)',
            'CREATE UNIQUE INDEX UNIQ_D87F7E0C8C73652176FF8CAA ON test (foo, bar)'
        );
    }

    public function getGenerateUniqueIndexSql()
    {
        return 'CREATE UNIQUE INDEX index_name ON test (test, test2)';
    }

    protected function getQuotedColumnInForeignKeySQL()
    {
        return array(
            'CREATE TABLE "quoted" ("create" VARCHAR(255) NOT NULL, foo VARCHAR(255) NOT NULL, "bar" VARCHAR(255) NOT NULL)',
            'ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_RESERVED_KEYWORD FOREIGN KEY ("create", foo, "bar") REFERENCES "foreign" ("create", bar, "foo-bar")',
            'ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_NON_RESERVED_KEYWORD FOREIGN KEY ("create", foo, "bar") REFERENCES foo ("create", bar, "foo-bar")',
            'ALTER TABLE "quoted" ADD CONSTRAINT FK_WITH_INTENDED_QUOTATION FOREIGN KEY ("create", foo, "bar") REFERENCES "foo-bar" ("create", bar, "foo-bar")',
        );
    }

    protected function getQuotedColumnInIndexSQL()
    {
        return array(
            'CREATE TABLE "quoted" ("create" VARCHAR(255) NOT NULL)',
            'CREATE INDEX IDX_22660D028FD6E0FB ON "quoted" ("create")'
        );
    }

    protected function getQuotedColumnInPrimaryKeySQL()
    {
        return array(
            'CREATE TABLE "quoted" ("create" VARCHAR(255) NOT NULL, PRIMARY KEY("create"))'
        );
    }

    protected function getBitAndComparisonExpressionSql($value1, $value2)
    {
        return 'BITAND(' . $value1 . ', ' . $value2 . ')';
    }

    protected  function getBitOrComparisonExpressionSql($value1, $value2)
    {
        return 'BITOR(' . $value1 . ', ' . $value2 . ')';
    }

    public function getCreateTableColumnCommentsSQL()
    {
        return array(
            "CREATE TABLE test (id INTEGER NOT NULL, PRIMARY KEY(id))",
        );
    }

    public function getAlterTableColumnCommentsSQL()
    {
        return array(
            "ALTER TABLE mytable ADD COLUMN quota INTEGER NOT NULL WITH DEFAULT ALTER foo foo VARCHAR(255) NOT NULL ALTER bar baz VARCHAR(255) NOT NULL",
            "CALL SYSPROC.ADMIN_CMD ('REORG TABLE mytable')"
        );
    }

    public function getCreateTableColumnTypeCommentsSQL()
    {
        return array(
            'CREATE TABLE test (id INTEGER NOT NULL, "data" CLOB(1M) NOT NULL, PRIMARY KEY(id))',
        );
    }

    public function testHasCorrectPlatformName()
    {
        $this->assertEquals('db2', $this->_platform->getName());
    }

    public function testGeneratesCreateTableSQLWithCommonIndexes()
    {
        $table = new Table('test');
        $table->addColumn('id', 'integer');
        $table->addColumn('name', 'string', array('length' => 50));
        $table->setPrimaryKey(array('id'));
        $table->addIndex(array('name'));
        $table->addIndex(array('id', 'name'), 'composite_idx');

        $this->assertEquals(
            array(
                'CREATE TABLE test (id INTEGER NOT NULL, name VARCHAR(50) NOT NULL, PRIMARY KEY(id))',
                'CREATE INDEX IDX_D87F7E0C5E237E06 ON test (name)',
                'CREATE INDEX composite_idx ON test (id, name)'
            ),
            $this->_platform->getCreateTableSQL($table)
        );
    }

    public function testGeneratesCreateTableSQLWithForeignKeyConstraints()
    {
        $table = new Table('test');
        $table->addColumn('id', 'integer');
        $table->addColumn('fk_1', 'integer');
        $table->addColumn('fk_2', 'integer');
        $table->setPrimaryKey(array('id'));
        $table->addForeignKeyConstraint('foreign_table', array('fk_1', 'fk_2'), array('pk_1', 'pk_2'));
        $table->addForeignKeyConstraint(
            'foreign_table2',
            array('fk_1', 'fk_2'),
            array('pk_1', 'pk_2'),
            array(),
            'named_fk'
        );

        $this->assertEquals(
            array(
                'CREATE TABLE test (id INTEGER NOT NULL, fk_1 INTEGER NOT NULL, fk_2 INTEGER NOT NULL)',
                'ALTER TABLE test ADD CONSTRAINT FK_D87F7E0C177612A38E7F4319 FOREIGN KEY (fk_1, fk_2) REFERENCES foreign_table (pk_1, pk_2)',
                'ALTER TABLE test ADD CONSTRAINT named_fk FOREIGN KEY (fk_1, fk_2) REFERENCES foreign_table2 (pk_1, pk_2)',
            ),
            $this->_platform->getCreateTableSQL($table, AbstractPlatform::CREATE_FOREIGNKEYS)
        );
    }

    public function testGeneratesCreateTableSQLWithCheckConstraints()
    {
        $table = new Table('test');
        $table->addColumn('id', 'integer');
        $table->addColumn('check_max', 'integer', array('platformOptions' => array('max' => 10)));
        $table->addColumn('check_min', 'integer', array('platformOptions' => array('min' => 10)));
        $table->setPrimaryKey(array('id'));

        $this->assertEquals(
            array(
                'CREATE TABLE test (id INTEGER NOT NULL, check_max INTEGER NOT NULL, check_min INTEGER NOT NULL, PRIMARY KEY(id), CHECK (check_max <= 10), CHECK (check_min >= 10))'
            ),
            $this->_platform->getCreateTableSQL($table)
        );
    }

    public function testGeneratesColumnTypesDeclarationSQL()
    {
        $fullColumnDef = array(
            'length' => 10,
            'fixed' => true,
            'unsigned' => true,
            'autoincrement' => true
        );

        $this->assertEquals('VARCHAR(255)', $this->_platform->getVarcharTypeDeclarationSQL(array()));
        $this->assertEquals('VARCHAR(10)', $this->_platform->getVarcharTypeDeclarationSQL(array('length' => 10)));
        $this->assertEquals('CHAR(255)', $this->_platform->getVarcharTypeDeclarationSQL(array('fixed' => true)));
        $this->assertEquals('CHAR(10)', $this->_platform->getVarcharTypeDeclarationSQL($fullColumnDef));

        $this->assertEquals('SMALLINT', $this->_platform->getSmallIntTypeDeclarationSQL(array()));
        $this->assertEquals('SMALLINT', $this->_platform->getSmallIntTypeDeclarationSQL(array(
            'unsigned' => true
        )));
        $this->assertEquals('SMALLINT GENERATED BY DEFAULT AS IDENTITY', $this->_platform->getSmallIntTypeDeclarationSQL($fullColumnDef));
        $this->assertEquals('INTEGER', $this->_platform->getIntegerTypeDeclarationSQL(array()));
        $this->assertEquals('INTEGER', $this->_platform->getIntegerTypeDeclarationSQL(array(
            'unsigned' => true
        )));
        $this->assertEquals('INTEGER GENERATED BY DEFAULT AS IDENTITY', $this->_platform->getIntegerTypeDeclarationSQL($fullColumnDef));
        $this->assertEquals('BIGINT', $this->_platform->getBigIntTypeDeclarationSQL(array()));
        $this->assertEquals('BIGINT', $this->_platform->getBigIntTypeDeclarationSQL(array(
            'unsigned' => true
        )));
        $this->assertEquals('BIGINT GENERATED BY DEFAULT AS IDENTITY', $this->_platform->getBigIntTypeDeclarationSQL($fullColumnDef));
        $this->assertEquals('BLOB(1M)', $this->_platform->getBlobTypeDeclarationSQL($fullColumnDef));
        $this->assertEquals('SMALLINT', $this->_platform->getBooleanTypeDeclarationSQL($fullColumnDef));
        $this->assertEquals('CLOB(1M)', $this->_platform->getClobTypeDeclarationSQL($fullColumnDef));
        $this->assertEquals('DATE', $this->_platform->getDateTypeDeclarationSQL($fullColumnDef));
        $this->assertEquals('TIMESTAMP(0) WITH DEFAULT', $this->_platform->getDateTimeTypeDeclarationSQL(array('version' => true)));
        $this->assertEquals('TIMESTAMP(0)', $this->_platform->getDateTimeTypeDeclarationSQL($fullColumnDef));
        $this->assertEquals('TIME', $this->_platform->getTimeTypeDeclarationSQL($fullColumnDef));
    }

    public function testInitializesDoctrineTypeMappings()
    {
        $this->_platform->initializeDoctrineTypeMappings();

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('smallint'));
        $this->assertSame('smallint', $this->_platform->getDoctrineTypeMapping('smallint'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('bigint'));
        $this->assertSame('bigint', $this->_platform->getDoctrineTypeMapping('bigint'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('integer'));
        $this->assertSame('integer', $this->_platform->getDoctrineTypeMapping('integer'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('time'));
        $this->assertSame('time', $this->_platform->getDoctrineTypeMapping('time'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('date'));
        $this->assertSame('date', $this->_platform->getDoctrineTypeMapping('date'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('varchar'));
        $this->assertSame('string', $this->_platform->getDoctrineTypeMapping('varchar'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('character'));
        $this->assertSame('string', $this->_platform->getDoctrineTypeMapping('character'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('clob'));
        $this->assertSame('text', $this->_platform->getDoctrineTypeMapping('clob'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('blob'));
        $this->assertSame('blob', $this->_platform->getDoctrineTypeMapping('blob'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('decimal'));
        $this->assertSame('decimal', $this->_platform->getDoctrineTypeMapping('decimal'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('double'));
        $this->assertSame('float', $this->_platform->getDoctrineTypeMapping('double'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('real'));
        $this->assertSame('float', $this->_platform->getDoctrineTypeMapping('real'));

        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('timestamp'));
        $this->assertSame('datetime', $this->_platform->getDoctrineTypeMapping('timestamp'));
    }

    public function testGeneratesDDLSnippets()
    {
        $this->assertEquals("CREATE DATABASE foobar", $this->_platform->getCreateDatabaseSQL('foobar'));
        $this->assertEquals("DROP DATABASE foobar", $this->_platform->getDropDatabaseSQL('foobar'));
        $this->assertEquals('DECLARE GLOBAL TEMPORARY TABLE', $this->_platform->getCreateTemporaryTableSnippetSQL());
        $this->assertEquals('TRUNCATE foobar IMMEDIATE', $this->_platform->getTruncateTableSQL('foobar'));
        $this->assertEquals('TRUNCATE foobar IMMEDIATE', $this->_platform->getTruncateTableSQL('foobar'), true);

        $viewSql = 'SELECT * FROM footable';
        $this->assertEquals('CREATE VIEW fooview AS ' . $viewSql, $this->_platform->getCreateViewSQL('fooview', $viewSql));
        $this->assertEquals('DROP VIEW fooview', $this->_platform->getDropViewSQL('fooview'));
    }

    public function testGeneratesCreateUnnamedPrimaryKeySQL()
    {
        $this->assertEquals(
            'ALTER TABLE foo ADD PRIMARY KEY (a, b)',
            $this->_platform->getCreatePrimaryKeySQL(
                new Index('any_pk_name', array('a', 'b'), true, true),
                'foo'
            )
        );
    }

    public function testGeneratesSQLSnippets()
    {
        $this->assertEquals('CURRENT DATE', $this->_platform->getCurrentDateSQL());
        $this->assertEquals('CURRENT TIME', $this->_platform->getCurrentTimeSQL());
        $this->assertEquals('CURRENT TIMESTAMP', $this->_platform->getCurrentTimestampSQL());
        $this->assertEquals("'1987/05/02' + 4 days", $this->_platform->getDateAddDaysExpression("'1987/05/02'", 4));
        $this->assertEquals("'1987/05/02' + 12 hours", $this->_platform->getDateAddHourExpression("'1987/05/02'", 12));
        $this->assertEquals("'1987/05/02' + 102 months", $this->_platform->getDateAddMonthExpression("'1987/05/02'", 102));
        $this->assertEquals("DAYS('1987/05/02') - DAYS('1987/04/01')", $this->_platform->getDateDiffExpression("'1987/05/02'", "'1987/04/01'"));
        $this->assertEquals("'1987/05/02' - 4 days", $this->_platform->getDateSubDaysExpression("'1987/05/02'", 4));
        $this->assertEquals("'1987/05/02' - 12 hours", $this->_platform->getDateSubHourExpression("'1987/05/02'", 12));
        $this->assertEquals("'1987/05/02' - 102 months", $this->_platform->getDateSubMonthExpression("'1987/05/02'", 102));
        $this->assertEquals(' WITH RR USE AND KEEP UPDATE LOCKS', $this->_platform->getForUpdateSQL());
        $this->assertEquals('LOCATE(substring_column, string_column)', $this->_platform->getLocateExpression('string_column', 'substring_column'));
        $this->assertEquals('LOCATE(substring_column, string_column)', $this->_platform->getLocateExpression('string_column', 'substring_column'));
        $this->assertEquals('LOCATE(substring_column, string_column, 1)', $this->_platform->getLocateExpression('string_column', 'substring_column', 1));
        $this->assertEquals('SUBSTR(column, 5)', $this->_platform->getSubstringExpression('column', 5));
        $this->assertEquals('SUBSTR(column, 5, 2)', $this->_platform->getSubstringExpression('column', 5, 2));
    }

    public function testModifiesLimitQuery()
    {
        $this->assertEquals(
            'SELECT * FROM user',
            $this->_platform->modifyLimitQuery('SELECT * FROM user', null, null)
        );

        $this->assertEquals(
            'SELECT db22.* FROM (SELECT ROW_NUMBER() OVER() AS DC_ROWNUM, db21.* FROM (SELECT * FROM user) db21) db22 WHERE db22.DC_ROWNUM BETWEEN 1 AND 10',
            $this->_platform->modifyLimitQuery('SELECT * FROM user', 10, 0)
        );

        $this->assertEquals(
            'SELECT db22.* FROM (SELECT ROW_NUMBER() OVER() AS DC_ROWNUM, db21.* FROM (SELECT * FROM user) db21) db22 WHERE db22.DC_ROWNUM BETWEEN 1 AND 10',
            $this->_platform->modifyLimitQuery('SELECT * FROM user', 10)
        );

        $this->assertEquals(
            'SELECT db22.* FROM (SELECT ROW_NUMBER() OVER() AS DC_ROWNUM, db21.* FROM (SELECT * FROM user) db21) db22 WHERE db22.DC_ROWNUM BETWEEN 6 AND 15',
            $this->_platform->modifyLimitQuery('SELECT * FROM user', 10, 5)
        );
        $this->assertEquals(
            'SELECT db22.* FROM (SELECT ROW_NUMBER() OVER() AS DC_ROWNUM, db21.* FROM (SELECT * FROM user) db21) db22 WHERE db22.DC_ROWNUM BETWEEN 6 AND 5',
            $this->_platform->modifyLimitQuery('SELECT * FROM user', 0, 5)
        );
    }

    public function testPrefersIdentityColumns()
    {
        $this->assertTrue($this->_platform->prefersIdentityColumns());
    }

    public function testSupportsIdentityColumns()
    {
        $this->assertTrue($this->_platform->supportsIdentityColumns());
    }

    public function testDoesNotSupportSavePoints()
    {
        $this->assertFalse($this->_platform->supportsSavepoints());
    }

    public function testDoesNotSupportReleasePoints()
    {
        $this->assertFalse($this->_platform->supportsReleaseSavepoints());
    }

    public function testDoesNotSupportCreateDropDatabase()
    {
        $this->assertFalse($this->_platform->supportsCreateDropDatabase());
    }

    public function testReturnsSQLResultCasing()
    {
        $this->assertSame('COL', $this->_platform->getSQLResultCasing('cOl'));
    }

    protected function getBinaryDefaultLength()
    {
        return 1;
    }

    protected function getBinaryMaxLength()
    {
        return 32704;
    }

    public function testReturnsBinaryTypeDeclarationSQL()
    {
        $this->assertSame('VARBINARY(1)', $this->_platform->getBinaryTypeDeclarationSQL(array()));
        $this->assertSame('VARBINARY(255)', $this->_platform->getBinaryTypeDeclarationSQL(array('length' => 0)));
        $this->assertSame('VARBINARY(32704)', $this->_platform->getBinaryTypeDeclarationSQL(array('length' => 32704)));
        $this->assertSame('BLOB(1M)', $this->_platform->getBinaryTypeDeclarationSQL(array('length' => 32705)));

        $this->assertSame('BINARY(1)', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true)));
        $this->assertSame('BINARY(255)', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true, 'length' => 0)));
        $this->assertSame('BINARY(32704)', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true, 'length' => 32704)));
        $this->assertSame('BLOB(1M)', $this->_platform->getBinaryTypeDeclarationSQL(array('fixed' => true, 'length' => 32705)));
    }
}
