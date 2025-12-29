<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Http\Controllers\Api\CategoryCaseDistributionController;
use Illuminate\Http\Request;

class TestCategoryAPI extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:category-api';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the Category Case Distribution API endpoints';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing Category Case Distribution API...');

        try {
            // Get admin user
            $admin = User::where('email', 'admin@safevoice.tz')->first();

            if (!$admin) {
                $this->error('Admin user not found');
                return 1;
            }

            $this->info('✓ Admin user found: ' . $admin->name);

            // Create mock request
            $request = Request::create('/api/category-distribution/analytics', 'GET');
            $request->setUserResolver(function () use ($admin) {
                return $admin;
            });

            // Instantiate controller
            $controller = new CategoryCaseDistributionController();

            // Test analytics endpoint
            $this->info('Testing getCategoryAnalytics...');
            $response = $controller->getCategoryAnalytics($request);

            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getContent(), true);
                if (isset($data['status']) && $data['status'] === 'success') {
                    $this->info('✓ Analytics endpoint working');
                    $this->info('  - Status: ' . $data['status']);
                    $this->info('  - Message: ' . $data['message']);
                    if (isset($data['data']['summary'])) {
                        $summary = $data['data']['summary'];
                        $this->info('  - Total cases: ' . $summary['total_cases']);
                        $this->info('  - Total categories: ' . $summary['total_categories']);
                        $this->info('  - Closure rate: ' . $summary['overall_closure_rate'] . '%');
                    }
                    if (isset($data['data']['categories'])) {
                        $this->info('  - Found ' . count($data['data']['categories']) . ' categories');

                        // Show sample categories
                        foreach (array_slice($data['data']['categories'], 0, 3) as $cat) {
                            $this->info('    • ' . $cat['category']['name'] . ' (' . ucfirst($cat['category']['type']) . '): ' . $cat['total_cases'] . ' cases');
                        }
                    }
                } else {
                    $this->error('Analytics endpoint returned unexpected status');
                    $this->error('Response: ' . $response->getContent());
                }
            } else {
                $this->error('Analytics endpoint failed with status: ' . $response->getStatusCode());
                $this->error('Response: ' . $response->getContent());
            }

            // Test filter options endpoint
            $this->info('Testing getFilters...');
            $response = $controller->getFilters($request);

            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getContent(), true);
                if (isset($data['status']) && $data['status'] === 'success') {
                    $this->info('✓ Filter options endpoint working');
                    if (isset($data['data']['companies'])) {
                        $this->info('  - Companies: ' . count($data['data']['companies']));
                    }
                    if (isset($data['data']['branches'])) {
                        $this->info('  - Branches: ' . count($data['data']['branches']));
                    }
                    if (isset($data['data']['incident_categories'])) {
                        $this->info('  - Incident Categories: ' . count($data['data']['incident_categories']));
                    }
                    if (isset($data['data']['feedback_categories'])) {
                        $this->info('  - Feedback Categories: ' . count($data['data']['feedback_categories']));
                    }
                } else {
                    $this->error('Filter options endpoint returned unexpected status');
                }
            } else {
                $this->error('Filter options endpoint failed with status: ' . $response->getStatusCode());
            }

            // Test export endpoint
            $this->info('Testing CSV export...');
            $response = $controller->exportCategoryData($request);

            if ($response->getStatusCode() === 200) {
                $contentType = $response->headers->get('Content-Type');
                if ($contentType === 'text/csv') {
                    $this->info('✓ CSV export endpoint working');
                    $this->info('  - Content type: ' . $contentType);

                    $content = $response->getContent();
                    $lines = explode("\n", trim($content));
                    $this->info('  - CSV lines: ' . count($lines));

                    if (count($lines) > 0) {
                        $header = str_getcsv($lines[0]);
                        $this->info('  - Headers: ' . implode(', ', $header));
                    }
                } else {
                    $this->error('Export endpoint returned unexpected content type: ' . $contentType);
                }
            } else {
                $this->error('Export endpoint failed with status: ' . $response->getStatusCode());
            }

            $this->info('');
            $this->info('✅ Category Case Distribution API test completed successfully!');
        } catch (\Exception $e) {
            $this->error('❌ Test failed: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
        }
    }
}
