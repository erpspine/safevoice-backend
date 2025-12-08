<?php

// Script to browse case message attachments
echo "Case Attachments Browser\n";
echo "=======================\n\n";

$caseId = '01k8eavptnrmchjx15m57v2fke'; // Your case ID
$attachmentsPath = __DIR__ . "/storage/app/private/case-messages/{$caseId}";

try {
    if (!is_dir($attachmentsPath)) {
        echo "❌ Case directory not found: {$attachmentsPath}\n";
        exit(1);
    }

    echo "📁 Browsing attachments for case: {$caseId}\n";
    echo "📂 Directory: {$attachmentsPath}\n\n";

    $files = scandir($attachmentsPath);
    $attachments = array_filter($files, function ($file) use ($attachmentsPath) {
        return $file !== '.' && $file !== '..' && is_file($attachmentsPath . '/' . $file);
    });

    if (empty($attachments)) {
        echo "📭 No attachments found in this case.\n";
        exit(0);
    }

    echo "📎 Found " . count($attachments) . " attachment(s):\n";
    echo "═══════════════════════════════════════════\n\n";

    foreach ($attachments as $file) {
        $filePath = $attachmentsPath . '/' . $file;
        $fileSize = filesize($filePath);
        $fileDate = date('Y-m-d H:i:s', filemtime($filePath));
        $fileExtension = pathinfo($file, PATHINFO_EXTENSION);

        // Get MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        echo "📄 File: {$file}\n";
        echo "   📅 Modified: {$fileDate}\n";
        echo "   📏 Size: " . formatFileSize($fileSize) . "\n";
        echo "   🏷️  Type: {$mimeType}\n";
        echo "   📁 Extension: .{$fileExtension}\n";
        echo "   🔗 Full Path: {$filePath}\n";
        echo "   ────────────────────────────────────────\n\n";
    }

    // Summary
    $totalSize = array_sum(array_map(function ($file) use ($attachmentsPath) {
        return filesize($attachmentsPath . '/' . $file);
    }, $attachments));

    echo "📊 Summary:\n";
    echo "   • Total Files: " . count($attachments) . "\n";
    echo "   • Total Size: " . formatFileSize($totalSize) . "\n";
    echo "   • Case ID: {$caseId}\n";
    echo "   • Directory: {$attachmentsPath}\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

function formatFileSize($bytes, $precision = 2)
{
    $units = array('B', 'KB', 'MB', 'GB', 'TB');

    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }

    return round($bytes, $precision) . ' ' . $units[$i];
}
