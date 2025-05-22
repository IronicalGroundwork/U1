<?php
require_once __DIR__ . '/ApiClient.php';
class OzonService {
    public static function getWarehouses(string $clientId, string $apiKey): array {
        $headers = [
            "Client-Id: $clientId",
            "Api-Key: $apiKey",
            "Content-Type: application/json"
        ];    
        
        return ApiClient::callApi(
                "https://api-seller.ozon.ru/v1/warehouse/list",
                $headers,
                "POST"
        );
        
    }

    public static function getProducts(string $clientId, string $apiKey): array {
        $headers = [
            "Client-Id: $clientId",
            "Api-Key: $apiKey",
            "Content-Type: application/json"
        ];   
        
        $total = 0;
        $lastId = "";
        $response = array(); 
        
        do {
            $data = array(
                "filter" => array(
                    "visibility" => "ALL"
                ),
                "last_id" => $lastId,
                "limit" => 800
            );  
            $tmp = ApiClient::callApi(
                "https://api-seller.ozon.ru/v4/product/info/attributes",
                $headers,
                "POST",
                $data
            );
            $result = $tmp["data"]["result"];
            $lastId = $tmp["data"]["last_id"];
            $total = $tmp["data"]["total"];

            $response = array_merge($response, $result);

        } while ($total != COUNT($response));
        
        return $response;
    }

    public static function getStocks(string $clientId, string $apiKey, array $data): array {
        $headers = [
            "Client-Id: $clientId",
            "Api-Key: $apiKey",
            "Content-Type: application/json"
        ];   
        
        $chunkSize = 500;
        $chunks = array_chunk($data, $chunkSize);

        $response = array();
        
        foreach ($chunks as $index => $chunk) {

            $dataSku["sku"]=$chunk;

            $tmp = ApiClient::callApi(
                "https://api-seller.ozon.ru/v1/product/info/stocks-by-warehouse/fbs",
                $headers,
                "POST",
                $dataSku
            );

            $result = $tmp["data"]["result"];
            $response = array_merge($response, $result);
        }
        return $response;
    }

    public static function updateStocks(string $clientId, string $apiKey, array $data): array {
        $headers = [
            "Client-Id: $clientId",
            "Api-Key: $apiKey",
            "Content-Type: application/json"
        ];    
        
        $chunkSize = 100;
        $chunks = array_chunk($data, $chunkSize, true);

        $response = array();
        
        foreach ($chunks as $index => $chunk) {

            if (count($response)>0) {
                sleep(30);
            }
            $dataStocks["stocks"]=$chunk;

            $tmp = ApiClient::callApi(
                "https://api-seller.ozon.ru/v2/products/stocks",
                $headers,
                "POST",
                $dataStocks
            );

            if (isset($tmp["data"]["code"])) 
            {
                return $tmp["data"];   
            }

            $result = $tmp["data"]["result"];
            $response = array_merge($response, $result);

        }
        $res["result"] = $response;
        return $res;
    }
}
?>