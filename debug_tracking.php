<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\CaseModel;

try {
    echo "Testing exact tracking method logic...\n\n";

    $accessId = 'CASE-DMW9TPQS';
    $accessPassword = 'SSUfWCR5P4Xx';

    echo "1. Finding case by access_id...\n";
    $case = CaseModel::where('access_id', $accessId)->first();

    if (!$case) {
        echo "ERROR: Case not found\n";
        exit;
    }
    echo "✓ Case found: " . $case->id . "\n\n";

    echo "2. Verifying password...\n";
    if (!password_verify($accessPassword, $case->access_password)) {
        echo "ERROR: Password verification failed\n";
        echo "Stored hash: " . $case->access_password . "\n";
        echo "Input password: " . $accessPassword . "\n";
        exit;
    }
    echo "✓ Password verified\n\n";

    echo "3. Loading relationships...\n";
    $case->load([
        'company:id,name',
        'branch:id,name',
        'department:id,name',
        'incidentCategory:id,name',
        'files:id,case_id,original_name,file_type,file_size,is_confidential,created_at',
        'involvedParties:id,case_id,name,involvement_type'
    ]);
    echo "✓ Relationships loaded\n\n";

    echo "4. Building response array...\n";
    $response = [
        'status' => 'success',
        'data' => [
            'case_id' => $case->id,
            'case_number' => $case->case_token,
            'title' => $case->title,
            'description' => $case->description,
            'status' => $case->status,
            'priority' => $case->priority, // This might be the issue - integer vs string
            'severity_level' => $case->severity_level, // This might be the issue too
            'location' => $case->location,
            'incident_date' => $case->incident_date,
            'submitted_at' => $case->created_at,
            'last_updated' => $case->updated_at,
            'company' => $case->company,
            'branch' => $case->branch,
            'department' => $case->department,
            'category' => $case->incidentCategory,
            'files_count' => $case->files->count(),
            'parties_count' => $case->involvedParties->count(),
            'is_anonymous' => $case->is_anonymous,
            'follow_up_required' => $case->follow_up_required,
            'resolution_note' => $case->resolution_note,
            'resolved_at' => $case->resolved_at,
            'timeline' => [
                'submitted' => $case->created_at,
                'last_update' => $case->updated_at,
                'resolved' => $case->resolved_at
            ]
        ]
    ];
    echo "✓ Response array built\n\n";

    echo "5. Converting to JSON...\n";
    $json = json_encode($response);
    if ($json === false) {
        echo "ERROR: JSON encoding failed - " . json_last_error_msg() . "\n";
        exit;
    }
    echo "✓ JSON encoded successfully\n\n";

    echo "SUCCESS! Full response:\n";
    echo $json . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
