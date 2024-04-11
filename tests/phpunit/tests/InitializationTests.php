<?php
use PHPUnit\Framework\TestCase;

class InitializationTests extends TestCase {
	public function test_databaseInitialized() {
		global $aspen_db;
		$this->assertNotNull($aspen_db);
	}

	public function test_rootDir() {
		$this->assertEquals('C:\web\aspen-discovery\code\web', ROOT_DIR);
	}

	public function test_getGitBranch() {
		$gitBranch = getGitBranch();
		$this->assertNotNull($gitBranch);
		$this->assertMatchesRegularExpression('/\d\d\.\d\d\.\d\d/', $gitBranch);
	}

	public function test_solrRunning() {
		require_once __DIR__ . '/../../../code/web/sys/SolrUtils.php';
		SolrUtils::startSolr();
		sleep(30);

		$solrSearcher = SearchObjectFactory::initSearchObject('GroupedWork');
		$pingResult = $solrSearcher->ping(true);
		$this->assertTrue($pingResult);
	}
}