<?php
/**
 * Created by PhpStorm.
 * User: marc
 * Date: 13/06/2017
 * Time: 19:02
 */

namespace Keboola\DbExtractor;

use Symfony\Component\Filesystem\Filesystem;
use Keboola\DbExtractor\Test\ExtractorTest;

abstract class AbstractRedshiftTest extends ExtractorTest
{
    const TESTING_SCHEMA_NAME = 'testing';

    public function setUp()
    {
        $fs = new Filesystem();
        $fs->remove($this->dataDir . '/out/tables');
        $fs->mkdir($this->dataDir . '/out/tables');

        if (!defined('APP_NAME')) {
            define('APP_NAME', 'ex-db-redshift');
        }
        $this->initRedshiftData($this->getConfig('redshift'));
    }

    private function initRedshiftData(array $config)
    {
        $pdo = new \PDO(
            "pgsql:dbname={$config['parameters']['db']['database']};port=5439;host=" . $config['parameters']['db']['host'],
            $config['parameters']['db']['user'],
            $config['parameters']['db']['#password']
        );
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $pdo->query(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', self::TESTING_SCHEMA_NAME));
        $pdo->query("CREATE SCHEMA \"" . self::TESTING_SCHEMA_NAME. "\"");
        $pdo->query("CREATE TABLE IF NOT EXISTS \"" . self::TESTING_SCHEMA_NAME . "\".escaping 
                      (col1 VARCHAR NOT NULL DEFAULT 'a', 
                      col2 VARCHAR NOT NULL DEFAULT 'b', 
                      col3 VARCHAR NULL,
                      PRIMARY KEY (col1, col2));");
        $pdo->query("CREATE TABLE IF NOT EXISTS \"" . self::TESTING_SCHEMA_NAME . "\".batch 
                      (id INT NOT NULL, 
                      name VARCHAR NOT NULL DEFAULT 'a', 
                      code VARCHAR NOT NULL DEFAULT 'b',
                      PRIMARY KEY (id));");

        $credStr = "aws_access_key_id={$config['aws']['s3key']};aws_secret_access_key={$config['aws']['s3secret']}";

        $qry = "COPY \"" . self::TESTING_SCHEMA_NAME. "\".escaping ";
        $qry .= "FROM 's3://{$config["aws"]["bucket"]}/escaping.csv' CREDENTIALS '$credStr' DELIMITER ',' QUOTE '\"' CSV IGNOREHEADER 1";
        $pdo->query($qry);

        $qry = "COPY \"" . self::TESTING_SCHEMA_NAME. "\".batch ";
        $qry .= "FROM 's3://{$config["aws"]["bucket"]}/batch.csv' CREDENTIALS '$credStr' DELIMITER ',' QUOTE '\"' CSV IGNOREHEADER 1";
        $pdo->query($qry);
    }

    public function getConfig($driver = 'redshift')
    {
        $config = parent::getConfig($driver);
        if (getenv('AWS_ACCESS_KEY')) {
            $config['aws']['s3key'] = getenv('AWS_ACCESS_KEY');
        }
        if (getenv('AWS_SECRET_KEY')) {
            $config['aws']['s3secret'] = getenv('AWS_SECRET_KEY');
        }
        if (getenv('AWS_REGION')) {
            $config['aws']['region'] = getenv('AWS_REGION');
        }
        if (getenv('AWS_S3_BUCKET')) {
            $config['aws']['bucket'] = getenv('AWS_S3_BUCKET');
        }

        $config['parameters']['extractor_class'] = 'Redshift';
        return $config;
    }

    public function getRedshiftPrivateKey()
    {
        // docker-compose .env file does not support new lines in variables so we have to modify the key https://github.com/moby/moby/issues/12997
        return str_replace('"', '', str_replace('\n', "\n", $this->getEnv('redshift', 'DB_SSH_KEY_PRIVATE')));
    }

}