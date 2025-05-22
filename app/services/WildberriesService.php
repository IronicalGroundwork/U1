<?php
require_once __DIR__ . '/ApiClient.php';
class WildberriesService  {
    
    public static function getSellerInfo(string $token): array {
        $headers = [
            "Authorization: $token", 
            "Content-Type:application/json"
        ];    
        
        return ApiClient::callApi(
                "https://common-api.wildberries.ru/api/v1/seller-info",
                $headers,
                "GET"
        );        
    }

    public static function getWarehouses(string $token): array {
        $headers = [
            "Authorization: $token", 
            "Content-Type:application/json"
        ];    
        
        return ApiClient::callApi(
                "https://marketplace-api.wildberries.ru/api/v3/warehouses",
                $headers,
                "GET"
        );        
    }

    public static function getProducts(string $token): array {
        $headers = [
            "Authorization: $token", 
            "Content-Type:application/json"
        ];     
        
        $updatedAt ="";
        $nmId = "";
        $limit = 100;
        $total = 0;
        
        $response = array(); 
        
        $data = array(
            "settings" => array(
                "cursor" => array(
                    "limit" => $limit
                ),
                "filter" => array(
                    "withPhoto" => -1
                )
            )
        ); 
        
        do {

            if ($updatedAt != "" & $nmId !="") 
            {
                $data["settings"]["cursor"]["updatedAt"] = $updatedAt;
                $data["settings"]["cursor"]["nmID"] = $nmId;
            }
            
            $tmp = ApiClient::callApi(
                "https://content-api.wildberries.ru/content/v2/get/cards/list",
                $headers,
                "POST",
                $data
            );

            $cards = $tmp["data"]["cards"];
            $updatedAt = $tmp["data"]["cursor"]["updatedAt"];
            $nmId = $tmp["data"]["cursor"]["nmID"];
            $total = $tmp["data"]["cursor"]["total"];
        
            $response = array_merge($response, $cards);
            
        } while ($total == $limit);
        
        return $response;
    }

    public static function getStocks(string $token, string $warehouseId, array $data): array {
        $headers = [
            "Authorization: $token", 
            "Content-Type:application/json"
        ];      
        
        $chunkSize = 1000;
        $chunks = array_chunk($data, $chunkSize);

        $response = array();
        
        foreach ($chunks as $index => $chunk) {

            $dataSku["skus"]=$chunk;

            $tmp = ApiClient::callApi(
                "https://marketplace-api.wildberries.ru/api/v3/stocks/$warehouseId",
                $headers,
                "POST",
                $dataSku
            );
            $stocks = $tmp["data"]["stocks"];
            $response = array_merge($response, $stocks);
        }
        return $response;
    }

    public static function updateStocks(string $token, string $warehouseId, array $data): array {
        $headers = [
            "Authorization: $token", 
            "Content-Type:application/json"
        ];   
                
        $chunkSize = 1000;
        $chunks = array_chunk($data, $chunkSize, true);

        $response = array();
        
        foreach ($chunks as $index => $chunk) {

            $dataStocks["stocks"]=$chunk;

            $tmp = ApiClient::callApi(
                "https://marketplace-api.wildberries.ru/api/v3/stocks/$warehouseId",
                $headers,
                "PUT",
                $dataStocks
            );

            if (isset($tmp["data"]["code"])) 
            {
                return $tmp["data"];    
            }
            $response = $tmp;
        }
        return $response;
    }
}
?>