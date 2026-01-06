<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Department;
use App\Models\SectorDepartmentTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DepartmentTemplateService
{
    /**
     * Sync departments for a company based on its sector.
     * - Adds new departments from templates that don't exist
     * - Removes template-based departments that no longer exist in templates
     * - Preserves custom departments (those without department_code)
     */
    public function syncDepartmentsFromSector(Company $company): array
    {
        if (!$company->sector) {
            return [
                'added' => [],
                'removed' => [],
                'preserved' => [],
                'message' => 'Company has no sector assigned',
            ];
        }

        $templates = SectorDepartmentTemplate::where('sector', $company->sector)
            ->where('status', true)
            ->orderBy('sort_order')
            ->orderBy('department_name')
            ->get();

        if ($templates->isEmpty()) {
            Log::info("No department templates found for sector: {$company->sector}");
            return [
                'added' => [],
                'removed' => [],
                'preserved' => [],
                'message' => "No templates found for sector: {$company->sector}",
            ];
        }

        $result = [
            'added' => [],
            'removed' => [],
            'preserved' => [],
        ];

        DB::transaction(function () use ($company, $templates, &$result) {
            // Get existing departments for this company
            $existingDepartments = Department::where('company_id', $company->id)
                ->withTrashed()
                ->get()
                ->keyBy('department_code');

            // Track template codes
            $templateCodes = $templates->pluck('department_code')->toArray();

            // Process each template
            foreach ($templates as $template) {
                $existingDept = $existingDepartments->get($template->department_code);

                if ($existingDept) {
                    // Restore if soft-deleted
                    if ($existingDept->trashed()) {
                        $existingDept->restore();
                        $result['added'][] = [
                            'code' => $template->department_code,
                            'name' => $template->department_name,
                            'action' => 'restored',
                        ];
                    } else {
                        $result['preserved'][] = [
                            'code' => $template->department_code,
                            'name' => $existingDept->name,
                        ];
                    }
                } else {
                    // Create new department
                    Department::create([
                        'company_id' => $company->id,
                        'name' => $template->department_name,
                        'department_code' => $template->department_code,
                        'description' => $template->description,
                        'status' => true,
                    ]);
                    $result['added'][] = [
                        'code' => $template->department_code,
                        'name' => $template->department_name,
                        'action' => 'created',
                    ];
                }
            }

            // Soft-delete template-based departments that are no longer in templates
            foreach ($existingDepartments as $code => $department) {
                if ($code && !in_array($code, $templateCodes)) {
                    if (!$department->trashed()) {
                        $department->delete();
                        $result['removed'][] = [
                            'code' => $code,
                            'name' => $department->name,
                            'action' => 'soft_deleted',
                        ];
                    }
                }
            }
        });

        $result['message'] = sprintf(
            'Sync completed: %d added, %d removed, %d preserved',
            count($result['added']),
            count($result['removed']),
            count($result['preserved'])
        );

        Log::info("Departments synced for company {$company->id}", $result);

        return $result;
    }

    /**
     * Create departments from sector templates for a new company.
     * This is used when a company is first created.
     */
    public function createDepartmentsFromSector(Company $company): array
    {
        if (!$company->sector) {
            return [
                'created' => [],
                'message' => 'Company has no sector assigned',
            ];
        }

        $templates = SectorDepartmentTemplate::where('sector', $company->sector)
            ->where('status', true)
            ->orderBy('sort_order')
            ->orderBy('department_name')
            ->get();

        if ($templates->isEmpty()) {
            Log::info("No department templates found for sector: {$company->sector}");
            return [
                'created' => [],
                'message' => "No templates found for sector: {$company->sector}",
            ];
        }

        $result = ['created' => []];

        DB::transaction(function () use ($company, $templates, &$result) {
            foreach ($templates as $template) {
                $department = Department::create([
                    'company_id' => $company->id,
                    'name' => $template->department_name,
                    'department_code' => $template->department_code,
                    'description' => $template->description,
                    'status' => true,
                ]);

                $result['created'][] = [
                    'id' => $department->id,
                    'code' => $template->department_code,
                    'name' => $template->department_name,
                ];
            }
        });

        $result['message'] = count($result['created']) . ' departments created';

        Log::info("Departments created for company {$company->id}", $result);

        return $result;
    }

    /**
     * Reset departments to match sector templates (removes all and recreates).
     * Warning: This will remove custom departments too!
     */
    public function resetDepartmentsFromSector(Company $company): array
    {
        if (!$company->sector) {
            return [
                'deleted' => 0,
                'created' => [],
                'message' => 'Company has no sector assigned',
            ];
        }

        $result = [
            'deleted' => 0,
            'created' => [],
        ];

        DB::transaction(function () use ($company, &$result) {
            // Force delete all existing departments
            $result['deleted'] = Department::where('company_id', $company->id)->forceDelete();

            // Create fresh from templates
            $createResult = $this->createDepartmentsFromSector($company);
            $result['created'] = $createResult['created'];
        });

        $result['message'] = sprintf(
            'Reset completed: %d deleted, %d created',
            $result['deleted'],
            count($result['created'])
        );

        return $result;
    }

    /**
     * Sync all companies in a specific sector with the latest templates.
     */
    public function syncAllCompaniesInSector(string $sector): array
    {
        $companies = Company::where('sector', $sector)->get();

        $results = [
            'total_companies' => $companies->count(),
            'synced' => 0,
            'failed' => 0,
            'details' => [],
        ];

        foreach ($companies as $company) {
            try {
                $syncResult = $this->syncDepartmentsFromSector($company);
                $results['synced']++;
                $results['details'][] = [
                    'company_id' => $company->id,
                    'company_name' => $company->name,
                    'status' => 'success',
                    'result' => $syncResult,
                ];
            } catch (\Exception $e) {
                $results['failed']++;
                $results['details'][] = [
                    'company_id' => $company->id,
                    'company_name' => $company->name,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}
