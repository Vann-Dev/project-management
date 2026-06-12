<?php

namespace Tests\Unit\Helpers;

use App\Helpers\JiraHelper;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

/**
 * Concrete class for testing JiraHelper trait
 */
class JiraHelperTestClass
{
    use JiraHelper;
}

class JiraHelperTest extends TestCase
{
    private JiraHelperTestClass $helper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->helper = new JiraHelperTestClass();
    }

    public function test_connect_to_jira_creates_client_with_correct_configuration(): void
    {
        $host = 'https://test-jira.atlassian.net';
        $username = 'test@example.com';
        $token = 'test-api-token-123';

        $client = $this->helper->connectToJira($host, $username, $token);

        // Verify client is created
        $this->assertInstanceOf(Client::class, $client);

        // Verify client configuration by inspecting config
        $config = $client->getConfig();

        $this->assertEquals($host, $config['base_uri']);

        // Verify headers are set correctly
        $this->assertArrayHasKey('headers', $config);
        $this->assertEquals('application/json', $config['headers']['Content-Type']);
        $this->assertEquals('application/json', $config['headers']['Accept']);

        // Verify Authorization header has correct format
        $expectedAuth = 'Basic ' . base64_encode($username . ':' . $token);
        $this->assertEquals($expectedAuth, $config['headers']['Authorization']);
    }

    public function test_connect_to_jira_encodes_credentials_correctly(): void
    {
        $host = 'https://jira.example.com';
        $username = 'user@domain.com';
        $token = 'secret-token';

        $client = $this->helper->connectToJira($host, $username, $token);
        $config = $client->getConfig();

        // Manually encode and verify
        $expectedEncoded = base64_encode($username . ':' . $token);
        $expectedHeader = 'Basic ' . $expectedEncoded;

        $this->assertEquals($expectedHeader, $config['headers']['Authorization']);

        // Verify it's actually base64 encoded
        $this->assertStringStartsWith('Basic ', $config['headers']['Authorization']);
    }

    public function test_get_jira_projects_returns_array_on_success(): void
    {
        // Create mock response data
        $mockProjects = [
            (object) [
                'id' => '10001',
                'key' => 'TEST',
                'name' => 'Test Project'
            ],
            (object) [
                'id' => '10002',
                'key' => 'DEMO',
                'name' => 'Demo Project'
            ]
        ];

        $responseBody = json_encode($mockProjects);
        $mockResponse = new Response(200, [], $responseBody);

        // Create mock client
        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('get')
            ->once()
            ->with('/rest/api/2/project')
            ->andReturn($mockResponse);

        $result = $this->helper->getJiraProjects($mockClient);

        // Verify result is an array and matches expected data
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('10001', $result[0]->id);
        $this->assertEquals('TEST', $result[0]->key);
        $this->assertEquals('Test Project', $result[0]->name);
    }

    public function test_get_jira_projects_returns_null_on_exception(): void
    {
        // Create mock client that throws exception
        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('get')
            ->once()
            ->with('/rest/api/2/project')
            ->andThrow(new RequestException(
                'Connection failed',
                new Request('GET', '/rest/api/2/project')
            ));

        $result = $this->helper->getJiraProjects($mockClient);

        // Should return null on exception
        $this->assertNull($result);
    }

    public function test_get_jira_tickets_by_project_returns_formatted_array_on_success(): void
    {
        // Create mock issue data
        $mockIssues = [
            (object) [
                'key' => 'TEST-1',
                'fields' => (object) [
                    'summary' => 'First test issue'
                ]
            ],
            (object) [
                'key' => 'TEST-2',
                'fields' => (object) [
                    'summary' => 'Second test issue'
                ]
            ]
        ];

        $mockResponseData = (object) [
            'total' => 2,
            'issues' => $mockIssues
        ];

        $responseBody = json_encode($mockResponseData);
        $mockResponse = new Response(200, [], $responseBody);

        // Create mock client
        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('get')
            ->once()
            ->with('/rest/api/2/search?jql=project=TEST')
            ->andReturn($mockResponse);

        $result = $this->helper->getJiraTicketsByProject($mockClient, ['TEST']);

        // Verify result structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('TEST', $result);
        $this->assertEquals(2, $result['TEST']['total']);
        $this->assertCount(2, $result['TEST']['issues']);

        // Verify formatted issues
        $this->assertEquals('TEST-1', $result['TEST']['issues'][0]['code']);
        $this->assertEquals('First test issue', $result['TEST']['issues'][0]['name']);
        $this->assertArrayHasKey('data', $result['TEST']['issues'][0]);
    }

    public function test_get_jira_tickets_by_project_returns_null_on_exception(): void
    {
        // Create mock client that throws exception
        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('get')
            ->once()
            ->andThrow(new RequestException(
                'API request failed',
                new Request('GET', '/rest/api/2/search')
            ));

        $result = $this->helper->getJiraTicketsByProject($mockClient, ['TEST']);

        // Should return null on exception
        $this->assertNull($result);
    }

    public function test_get_jira_ticket_details_extracts_key_from_url_and_returns_data(): void
    {
        // Mock issue data
        $mockIssue = (object) [
            'key' => 'TEST-123',
            'fields' => (object) [
                'summary' => 'Test issue details',
                'description' => 'Detailed description'
            ]
        ];

        $responseBody = json_encode($mockIssue);
        $mockResponse = new Response(200, [], $responseBody);

        // We need to partially mock the helper to mock connectToJira
        $helperMock = Mockery::mock(JiraHelperTestClass::class)->makePartial();

        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('get')
            ->once()
            ->with('/rest/api/2/issue/TEST-123')
            ->andReturn($mockResponse);

        $helperMock->shouldReceive('connectToJira')
            ->once()
            ->andReturn($mockClient);

        $url = 'https://jira.example.com/browse/TEST-123';
        $result = $helperMock->getJiraTicketDetails(
            'https://jira.example.com',
            'user@example.com',
            'token',
            $url
        );

        // Verify result
        $this->assertNotNull($result);
        $this->assertEquals('TEST-123', $result->key);
        $this->assertEquals('Test issue details', $result->fields->summary);
    }

    public function test_get_jira_ticket_details_returns_null_on_exception(): void
    {
        // Partially mock helper
        $helperMock = Mockery::mock(JiraHelperTestClass::class)->makePartial();

        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('get')
            ->once()
            ->andThrow(new RequestException(
                'Failed to fetch ticket',
                new Request('GET', '/rest/api/2/issue/TEST-123')
            ));

        $helperMock->shouldReceive('connectToJira')
            ->once()
            ->andReturn($mockClient);

        $result = $helperMock->getJiraTicketDetails(
            'https://jira.example.com',
            'user@example.com',
            'token',
            'https://jira.example.com/browse/TEST-123'
        );

        // Should return null on exception
        $this->assertNull($result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
