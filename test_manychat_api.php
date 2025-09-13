<?php
// Простой тест API для ManyChat интеграции

// Тест 1: Загрузка подписанного договора
echo "=== Тест загрузки подписанного договора ===\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/contract/upload-signed');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'signed_contract' => new CURLFile('test_signed_contract.pdf'),
    'contract_number' => '20250913-005'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n\n";

// Парсим ответ
$data = json_decode($response, true);
if ($data && isset($data['download_url'])) {
    echo "=== Тест скачивания подписанного договора ===\n";
    
    // Тест 2: Скачивание подписанного договора
    $filename = basename($data['download_url']);
    $downloadUrl = "http://localhost:8000/api/contract/download-signed/$filename";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $downloadUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true); // Только заголовки
    
    $headers = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Download HTTP Code: $httpCode\n";
    echo "Download URL: $downloadUrl\n";
    
    // Тест 3: Отправка в ManyChat
    echo "\n=== Тест отправки в ManyChat ===\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/contract/send-to-manychat');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'contract_number' => '20250913-005',
        'signed_file_url' => $data['download_url'],
        'manychat_webhook_url' => 'https://hooks.slack.com/services/TEST/WEBHOOK' // Тестовый webhook
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "ManyChat HTTP Code: $httpCode\n";
    echo "ManyChat Response: $response\n";
}

echo "\n=== Результаты тестирования ===\n";
echo "1. Загрузка подписанного договора: " . (isset($data['success']) && $data['success'] ? "✅ OK" : "❌ FAIL") . "\n";
echo "2. Генерация ссылки для ManyChat: " . (isset($data['download_url']) ? "✅ OK" : "❌ FAIL") . "\n";
echo "3. Поле для ManyChat: " . (isset($data['manychat_field']) ? $data['manychat_field'] : "❌ FAIL") . "\n";
echo "4. Значение для ManyChat: " . (isset($data['manychat_value']) ? "✅ OK" : "❌ FAIL") . "\n";
?>
