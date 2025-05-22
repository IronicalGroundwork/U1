<?php
class ApiClient {
    public static function callApi(
        string $url, 
        array $headers, 
        string $method = "GET", 
        $data = null): array
    {
        $ch = curl_init($url);
        
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HEADER => false,
        ]);
        
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                break;
            case 'PUT':
            case 'PATCH':
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
                break;
        }
        
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($error) {
            return ['error' => $error, 'http_code' => $httpCode];
        }
        
        $decodedResponse = json_decode($response, true) ?? $response;
        return ['data' => $decodedResponse,'http_code' => $httpCode];
    }
}
?>