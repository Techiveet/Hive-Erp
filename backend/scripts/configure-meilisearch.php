<?php

$host = 'http://meilisearch:7700';
$key = 'masterKey';

$tenants = ['apple', 'tesla', 'selam-bistro', 'nile-suites', 'afya-clinic'];

function updateIndex($indexUid, $settings) {
    global $host, $key;
    $ch = curl_init("$host/indexes/$indexUid/settings");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($settings));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $key",
        "Content-Type: application/json"
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "Index $indexUid: HTTP $httpCode - $response\n";
}

foreach ($tenants as $tenant) {
    // Conversations Index
    $convIndex = "tenant_{$tenant}_conversations";
    updateIndex($convIndex, [
        'searchableAttributes' => ['title', 'participants'],
        'filterableAttributes' => ['type', 'participants'],
    ]);

    // Messages Index
    $msgIndex = "tenant_{$tenant}_messages";
    updateIndex($msgIndex, [
        'searchableAttributes' => ['body'],
        'filterableAttributes' => ['conversation_id', 'sender_id', 'type'],
    ]);
}
