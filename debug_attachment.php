<?php

// Debug script to check message and attachment details
require_once 'vendor/autoload.php';

use App\Models\CaseMessage;

echo "Debug: Message and Attachment Lookup\n";
echo "===================================\n\n";

$caseId = '01k8eavptnrmchjx15m57v2fke';
$messageId = '01k8fk2y65j8k623hnqdbwmst5';
$filename = '01K8FK2Y5XVN746SC2TQKHECTG.pdf';

try {
    // Initialize Laravel
    $app = require_once 'bootstrap/app.php';
    $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

    echo "🔍 Looking for message: {$messageId}\n";
    echo "🗃️  In case: {$caseId}\n";
    echo "📎 Filename: {$filename}\n\n";

    // Find the message
    $message = CaseMessage::where('id', $messageId)
        ->where('case_id', $caseId)
        ->first();

    if (!$message) {
        echo "❌ Message not found!\n";
        echo "Let's check what messages exist for this case:\n\n";

        $messages = CaseMessage::where('case_id', $caseId)
            ->where('has_attachments', true)
            ->get(['id', 'message', 'has_attachments', 'attachments', 'created_at']);

        if ($messages->count() > 0) {
            echo "📋 Messages with attachments in this case:\n";
            foreach ($messages as $msg) {
                echo "   Message ID: {$msg->id}\n";
                echo "   Created: {$msg->created_at}\n";
                echo "   Text: " . substr($msg->message, 0, 50) . "...\n";
                if ($msg->attachments) {
                    echo "   Attachments:\n";
                    foreach ($msg->attachments as $att) {
                        echo "     - {$att['stored_name']} ({$att['original_name']})\n";
                    }
                }
                echo "   ────────────────────────────────────────\n\n";
            }
        } else {
            echo "❌ No messages with attachments found in this case!\n";
        }
        exit(1);
    }

    echo "✅ Message found!\n";
    echo "   Message ID: {$message->id}\n";
    echo "   Case ID: {$message->case_id}\n";
    echo "   Visibility: {$message->visibility}\n";
    echo "   Has Attachments: " . ($message->has_attachments ? 'Yes' : 'No') . "\n";
    echo "   Message: " . substr($message->message, 0, 100) . "...\n\n";

    if (!$message->has_attachments || !$message->attachments) {
        echo "❌ Message has no attachments!\n";
        exit(1);
    }

    echo "📎 Attachments in this message:\n";
    foreach ($message->attachments as $index => $attachment) {
        echo "   Attachment #{$index}:\n";
        echo "     - Original Name: {$attachment['original_name']}\n";
        echo "     - Stored Name: {$attachment['stored_name']}\n";
        echo "     - Path: {$attachment['path']}\n";
        echo "     - MIME Type: {$attachment['mime_type']}\n";
        echo "     - Size: {$attachment['size']} bytes\n";

        if ($attachment['stored_name'] === $filename) {
            echo "     ✅ THIS IS THE FILE WE'RE LOOKING FOR!\n";

            // Check if file exists on disk
            $fullPath = storage_path('app/private/' . $attachment['path']);
            if (file_exists($fullPath)) {
                echo "     ✅ File exists on disk: {$fullPath}\n";
                echo "     📏 Actual file size: " . filesize($fullPath) . " bytes\n";
            } else {
                echo "     ❌ File NOT found on disk: {$fullPath}\n";
            }
        }
        echo "   ────────────────────────────────────────\n\n";
    }

    // Check if the specific attachment exists
    $attachment = collect($message->attachments)->firstWhere('stored_name', $filename);

    if ($attachment) {
        echo "✅ Attachment found in message!\n";
        echo "   File path: {$attachment['path']}\n";

        // Test Laravel Storage
        if (\Illuminate\Support\Facades\Storage::disk('local')->exists($attachment['path'])) {
            echo "✅ Laravel Storage confirms file exists\n";
        } else {
            echo "❌ Laravel Storage says file does not exist\n";
        }
    } else {
        echo "❌ Attachment with filename '{$filename}' not found in message!\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
