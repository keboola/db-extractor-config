<?php

declare(strict_types=1);

namespace Keboola\DbExtractorConfig\Tests;

use Keboola\DbExtractorConfig\Config;
use Keboola\DbExtractorConfig\Configuration\ActionConfigRowDefinition;
use Keboola\DbExtractorConfig\Configuration\ConfigDefinition;
use Keboola\DbExtractorConfig\Configuration\ConfigRowDefinition;
use Keboola\DbExtractorConfig\Exception\UserException as ConfigUserException;
use Keboola\DbExtractorConfig\Test\AbstractConfigTest;

class ConfigTest extends AbstractConfigTest
{
    public const DRIVER = 'config';

    public function testConfig(): void
    {
        $configurationArray = [
            'parameters' => [
                'data_dir' => '/code/tests/Keboola/DbExtractor/../../data',
                'extractor_class' => 'MySQL',
                'db' => [
                    'host' => 'mysql',
                    'user' => 'root',
                    '#password' => 'rootpassword',
                    'database' => 'test',
                    'port' => 3306,
                ],
                'tables' => [
                    [
                        'id' => 1,
                        'name' => 'sales',
                        'query' => 'SELECT * FROM sales',
                        'outputTable' => 'in.c-main.sales',
                        'incremental' => false,
                        'primaryKey' => [],
                        'enabled' => true,
                        'columns' => [],
                    ],
                    [
                        'id' => 2,
                        'name' => 'escaping',
                        'query' => 'SELECT * FROM escaping',
                        'outputTable' => 'in.c-main.escaping',
                        'incremental' => false,
                        'primaryKey' => [
                            0 => 'orderId',
                        ],
                        'enabled' => true,
                        'columns' => [],
                    ],
                    [
                        'id' => 3,
                        'enabled' => true,
                        'name' => 'tableColumns',
                        'outputTable' => 'in.c-main.tableColumns',
                        'incremental' => false,
                        'primaryKey' => [],
                        'table' => [
                            'schema' => 'test',
                            'tableName' => 'sales',
                        ],
                        'columns' => [
                            0 => 'usergender',
                            1 => 'usercity',
                            2 => 'usersentiment',
                            3 => 'zipcode',
                        ],
                    ],
                ],
            ],
        ];

        $config = new Config($configurationArray, new ConfigDefinition());

        $this->assertEquals($configurationArray, $config->getData());
    }

    public function testConfigRow(): void
    {
        $configurationArray = [
            'parameters' => [
                'outputTable' => 'in.c-main.auto-increment-timestamp',
                'incremental' => true,
                'data_dir' => '/code/tests/Keboola/DbExtractor/../../data',
                'extractor_class' => 'MySQL',
                'table' => [
                    'tableName' => 'auto_increment_timestamp',
                    'schema' => 'test',
                ],
                'name' => 'auto-increment-timestamp',
                'incrementalFetchingColumn' => '_weird-I-d',
                'primaryKey' => [],
                'columns' => [],
                'enabled' => true,
            ],
        ];

        $config = new Config($configurationArray, new ConfigRowDefinition());

        $this->assertEquals($configurationArray, $config->getData());
    }

    public function testConfigActionRow(): void
    {
        $configurationArray = [
            'parameters' => [
                'data_dir' => '/code/tests/Keboola/DbExtractor/../../data',
                'extractor_class' => 'MySQL',
                'db' => [
                    'host' => 'mysql',
                    'user' => 'root',
                    '#password' => 'rootpassword',
                    'database' => 'test',
                    'port' => 3306,
                ],
            ],
        ];

        $config = new Config($configurationArray, new ActionConfigRowDefinition());

        $this->assertEquals($configurationArray, $config->getData());
    }

    public function testInvalidConfigQueryIncremental(): void
    {
        $configurationArray = [
            'parameters' => [
                'outputTable' => 'fake.output',
                'data_dir' => '/code/tests/Keboola/DbExtractor/../../data',
                'extractor_class' => 'MySQL',
                'query' => 'select 1 from test',
                'incrementalFetchingColumn' => 'test',
            ],
        ];

        $exceptionMessage =
            'The "incrementalFetchingColumn" is configured, ' .
            'but incremental fetching is not supported for custom query.';
        $this->expectException(ConfigUserException::class);
        $this->expectExceptionMessage($exceptionMessage);

        new Config($configurationArray, new ConfigRowDefinition());
    }

    public function testInvalidConfigTableOrQuery(): void
    {
        $configurationArray = [
            'parameters' => [
                'outputTable' => 'fake.output',
                'data_dir' => '/code/tests/Keboola/DbExtractor/../../data',
                'extractor_class' => 'MySQL',
            ],
        ];

        $this->expectException(ConfigUserException::class);
        $this->expectExceptionMessage('Table or query must be configured.');

        new Config($configurationArray, new ConfigRowDefinition());
    }

    public function testInvalidConfigsNeitherTableNorQueryWithNoName(): void
    {
        $configurationArray = [
            'parameters' => [
                'outputTable' => 'fake.output',
                'data_dir' => '/code/tests/Keboola/DbExtractor/../../data',
                'extractor_class' => 'MySQL',
                'table' => [
                    'schema' => 'test',
                ],
            ],
        ];

        $this->expectException(ConfigUserException::class);
        $this->expectExceptionMessage('The child node "tableName" at path "root.parameters.table" must be configured.');

        new Config($configurationArray, new ConfigRowDefinition());
    }

    public function testInvalidConfigsInvalidTableWithNoName(): void
    {
        $configurationArray = [
            'parameters' => [
                'outputTable' => 'in.c-main.auto-increment-timestamp',
                'data_dir' => '/code/tests/Keboola/DbExtractor/../../data',
                'extractor_class' => 'MySQL',
                'table' => [
                    'tableName' => 'auto_increment_timestamp',
                ],
            ],
        ];

        $this->expectException(ConfigUserException::class);
        $this->expectExceptionMessage('The child node "schema" at path "root.parameters.table" must be configured.');

        new Config($configurationArray, new ConfigRowDefinition());
    }

    public function testInvalidConfigsBothTableAndQuery(): void
    {
        $configurationArray = [
            'parameters' => [
                'outputTable' => 'fake.output',
                'data_dir' => '/code/tests/Keboola/DbExtractor/../../data',
                'extractor_class' => 'MySQL',
                'table' => [
                    'tableName' => 'test',
                    'schema' => 'test',
                ],
                'query' => 'select 1 from test',
            ],
        ];

        $exceptionMessage = 'Both table and query cannot be set together.';

        $this->expectException(ConfigUserException::class);
        $this->expectExceptionMessage($exceptionMessage);

        new Config($configurationArray, new ConfigRowDefinition());
    }

    public function testInvalidConfigsBothIncrFetchAndQueryWithNoName(): void
    {
        $configurationArray = [
            'parameters' => [
                'outputTable' => 'fake.output',
                'data_dir' => '/code/tests/Keboola/DbExtractor/../../data',
                'extractor_class' => 'MySQL',
                'incrementalFetchingColumn' => 'abc',
                'query' => 'select 1 limit 0',
            ],
        ];

        $exceptionMessage =
            'The "incrementalFetchingColumn" is configured, ' .
            'but incremental fetching is not supported for custom query.';

        $this->expectException(ConfigUserException::class);
        $this->expectExceptionMessage($exceptionMessage);

        new Config($configurationArray, new ConfigRowDefinition());
    }

    public function testTestConfigWithExtraKeysConfigDefinition(): void
    {
        $configurationArray = [
            'parameters' => [
                'data_dir' => '/code/tests/Keboola/DbExtractor/../../data',
                'extractor_class' => 'MySQL',
                'db' => [
                    'host' => 'mysql',
                    'user' => 'root',
                    '#password' => 'rootpassword',
                    'database' => 'test',
                    'port' => 3306,
                ],
                'tables' => [],
                'advancedMode' => true,
            ],
        ];

        $config = new Config($configurationArray, new ConfigDefinition());
        $this->assertEquals($configurationArray, $config->getData());
    }

    public function testTestConfigWithExtraKeysConfigRowDefinition(): void
    {
        $configurationArray = [
            'parameters' => [
                'data_dir' => '/code/tests/Keboola/DbExtractor/../../data',
                'extractor_class' => 'MySQL',
                'db' => [
                    'host' => 'mysql',
                    'user' => 'root',
                    '#password' => 'rootpassword',
                    'database' => 'test',
                    'port' => 3306,
                ],
                'query' => 'SELECT 1 FROM test',
                'outputTable' => 'testOutput',
                'columns' => [],
                'incremental' => false,
                'enabled' => true,
                'primaryKey' => [],
                'advancedMode' => true,
            ],
        ];

        $config = new Config($configurationArray, new ConfigRowDefinition());
        $this->assertEquals($configurationArray, $config->getData());
    }

    public function testTestConfigWithExtraKeysActionConfigRowDefinition(): void
    {
        $configurationArray = [
            'parameters' => [
                'data_dir' => '/code/tests/Keboola/DbExtractor/../../data',
                'extractor_class' => 'MySQL',
                'db' => [
                    'host' => 'mysql',
                    'user' => 'root',
                    '#password' => 'rootpassword',
                    'database' => 'test',
                    'port' => 3306,
                ],
                'advancedMode' => true,
            ],
        ];

        $config = new Config($configurationArray, new ActionConfigRowDefinition());
        $this->assertEquals($configurationArray, $config->getData());
    }

    public function testQueryCannotBeEmpty(): void
    {
        $configurationArray = [
            'parameters' => [
                'data_dir' => '/code/tests/Keboola/DbExtractor/../../data',
                'extractor_class' => 'MySQL',
                'db' => [
                    'host' => 'mysql',
                    'user' => 'root',
                    '#password' => 'rootpassword',
                    'database' => 'test',
                    'port' => 3306,
                ],
                'query' => '',
                'outputTable' => 'testOutput',
            ],
        ];

        $this->expectException(ConfigUserException::class);
        $this->expectExceptionMessage('"root.parameters.query" cannot contain an empty value, but got "".');
        new Config($configurationArray, new ConfigRowDefinition());
    }

    public function testTableSchemaCannotBeEmpty(): void
    {
        $configurationArray = [
            'parameters' => [
                'data_dir' => '/code/tests/Keboola/DbExtractor/../../data',
                'extractor_class' => 'MySQL',
                'outputTable' => 'in.c-main.auto-increment-timestamp',
                'table' => [
                    'tableName' => 'auto_increment_timestamp',
                    'schema' => '',
                ],
            ],
        ];

        $this->expectException(ConfigUserException::class);
        $this->expectExceptionMessage('"root.parameters.table.schema" cannot contain an empty value, but got "".');
        new Config($configurationArray, new ConfigRowDefinition());
    }

    public function testTableNameCannotBeEmpty(): void
    {
        $configurationArray = [
            'parameters' => [
                'data_dir' => '/code/tests/Keboola/DbExtractor/../../data',
                'extractor_class' => 'MySQL',
                'outputTable' => 'in.c-main.auto-increment-timestamp',
                'table' => [
                    'tableName' => '',
                    'schema' => 'schema',
                ],
            ],
        ];

        $this->expectException(ConfigUserException::class);
        $this->expectExceptionMessage('"root.parameters.table.tableName" cannot contain an empty value, but got "".');
        new Config($configurationArray, new ConfigRowDefinition());
    }

    public function testMissingIncrementalFetchingColumn(): void
    {
        $configurationArray = [
            'parameters' => [
                'data_dir' => '/code/tests/Keboola/DbExtractor/../../data',
                'extractor_class' => 'MySQL',
                'outputTable' => 'in.c-main.auto-increment-timestamp',
                'table' => [
                    'tableName' => 'name',
                    'schema' => 'schema',
                ],
                'incremental' => true,
            ],
        ];

        $this->expectException(ConfigUserException::class);
        $this->expectExceptionMessage(
            'The "incrementalFetchingColumn" must be configured, if is incremental fetching enabled.'
        );
        new Config($configurationArray, new ConfigRowDefinition());
    }

    public function testIncrementalFetchingColumnSetButIncrementalDisabled(): void
    {
        $configurationArray = [
            'parameters' => [
                'data_dir' => '/code/tests/Keboola/DbExtractor/../../data',
                'extractor_class' => 'MySQL',
                'outputTable' => 'in.c-main.auto-increment-timestamp',
                'table' => [
                    'tableName' => 'name',
                    'schema' => 'schema',
                ],
                'incremental' => false,
                'incrementalFetchingColumn' => 'name',
            ],
        ];

        $this->expectException(ConfigUserException::class);
        $this->expectExceptionMessage(
            'The "incrementalFetchingColumn" is configured, but incremental fetching is not enabled.'
        );
        new Config($configurationArray, new ConfigRowDefinition());
    }

    public function testIncrementalFetchingLimitSetButIncrementalDisabled(): void
    {
        $configurationArray = [
            'parameters' => [
                'data_dir' => '/code/tests/Keboola/DbExtractor/../../data',
                'extractor_class' => 'MySQL',
                'outputTable' => 'in.c-main.auto-increment-timestamp',
                'table' => [
                    'tableName' => 'name',
                    'schema' => 'schema',
                ],
                'incremental' => false,
                'incrementalFetchingLimit' => 100,
            ],
        ];

        $this->expectException(ConfigUserException::class);
        $this->expectExceptionMessage(
            'The "incrementalFetchingLimit" is configured, but incremental fetching is not enabled.'
        );
        new Config($configurationArray, new ConfigRowDefinition());
    }

    public function testEmptyColumnName(): void
    {
        $configurationArray = [
            'parameters' => [
                'data_dir' => '/code/tests/Keboola/DbExtractor/../../data',
                'extractor_class' => 'MySQL',
                'outputTable' => 'in.c-main.auto-increment-timestamp',
                'table' => [
                    'tableName' => 'name',
                    'schema' => 'schema',
                ],
                'columns' => ['abc', ''],
            ],
        ];

        $this->expectException(ConfigUserException::class);
        $this->expectExceptionMessage(
            'The path "root.parameters.columns.1" cannot contain an empty value, but got "".'
        );
        new Config($configurationArray, new ConfigRowDefinition());
    }

    public function testEmptyNameInPK(): void
    {
        $configurationArray = [
            'parameters' => [
                'data_dir' => '/code/tests/Keboola/DbExtractor/../../data',
                'extractor_class' => 'MySQL',
                'outputTable' => 'in.c-main.auto-increment-timestamp',
                'table' => [
                    'tableName' => 'name',
                    'schema' => 'schema',
                ],
                'primaryKey' => ['abc', ''],
            ],
        ];

        $this->expectException(ConfigUserException::class);
        $this->expectExceptionMessage(
            'The path "root.parameters.primaryKey.1" cannot contain an empty value, but got "".'
        );
        new Config($configurationArray, new ConfigRowDefinition());
    }
}
