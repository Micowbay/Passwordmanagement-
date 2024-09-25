<?php
// Azure Face API 的配置信息
$subscriptionKey = '4719624fc5ce43eea975209bc1a7fbe6';
$faceApiEndpoint = 'https://faceapi20240916.cognitiveservices.azure.com//face/v1.0/detect';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['image'])) {
    $imageData = $_POST['image'];

    // 移除前綴的 base64 標頭
    $imageData = str_replace('data:image/png;base64,', '', $imageData);
    $imageData = base64_decode($imageData);

    // 初始化 cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $faceApiEndpoint);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/octet-stream",
        "Ocp-Apim-Subscription-Key: $subscriptionKey"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $imageData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // 發送請求並處理回應
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo json_encode(['success' => false, 'message' => 'Face API request failed']);
        exit();
    }

    $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 檢查是否成功檢測到人臉
    if ($httpStatusCode == 200 && !empty($response)) {
        $faceData = json_decode($response, true);
        if (count($faceData) > 0) {
            // 假設人臉驗證通過
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
    } else {
        echo json_encode(['success' => false]);
    }
}
?>
