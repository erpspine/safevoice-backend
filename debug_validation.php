<?php
// Debug validation errors

function debugValidation()
{
    $url = 'http://127.0.0.1:8000/api/public/cases/submit';

    $data = [
        'company_id' => '01k7rjt9vjh4zdkv38nq4akwdj',
        'incident_category_id' => '01k87z5gjf91q70gx7hajr7k34',
        'description' => 'Test case',
        'priority' => 'medium',
        'access_id' => 'INC' . time(),
        'access_password' => '123456'
    ];

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content' => json_encode($data),
            'ignore_errors' => true
        ]
    ]);

    echo "Testing minimal case submission...\n";

    $result = file_get_contents($url, false, $context);

    echo "Response:\n";
    echo $result . "\n";

    if (isset($http_response_header)) {
        echo "\nHeaders:\n";
        foreach ($http_response_header as $header) {
            echo $header . "\n";
        }
    }
}

debugValidation();
