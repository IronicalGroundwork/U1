<?php
require_once __DIR__ . '/ApiClient.php';
class YandexService {

    public static function getBusinessesSettings(string $businessId, string $apiKey): array {
        $headers = [
            "Api-Key: $apiKey", 
            "Content-Type:application/json"
        ];    

        return ApiClient::callApi(
                "https://api.partner.market.yandex.ru/businesses/$businessId/settings",
                $headers,
                "POST"
        );        
    }

    public static function getCampaigns(string $apiKey): array {
        $headers = [
            "Api-Key: $apiKey", 
            "Content-Type:application/json"
        ];    
        
        return ApiClient::callApi(
                "https://api.partner.market.yandex.ru/campaigns",
                $headers,
                "GET"
        );        
    }

    public static function getProducts(string $businessId, string $apiKey): array {
        $headers = [
            "Api-Key: $apiKey", 
            "Content-Type:application/json"
        ];  
        
        $pageToken = "";
        $limit = 200;
        $response = array();
  
        do {
            $data = array(
                "archived" => false
            );
            
            $get = [
                "page_token" => $pageToken,
                "limit" => $limit
            ];
            $url ="https://api.partner.market.yandex.ru/businesses/$businessId/offer-mappings?".http_build_query($get);


            
            $tmp = ApiClient::callApi(
                $url,
                $headers,
                "POST",
                $data
            );
            $offerMappings = $tmp["data"]["result"]["offerMappings"];
            $pageToken  = $tmp["data"]["result"]["paging"]["nextPageToken"] ?? '';

            $response = array_merge($response, $offerMappings);
            
        } while ($pageToken != "");
        
        return $response;
        
    }

    public static function getStocks(string $campaignId, string $apiKey, array $data = null): array {
        $headers = [
            "Api-Key: $apiKey", 
            "Content-Type:application/json"
        ]; 
        
        if ($data == null) {
        
            $pageToken = "";
            $limit = 200;
            $response = array();
      
            do {
                $data = array(
                    "withTurnover" => false,
                    "archived" => false
                );
                
                $get = [
                    "page_token" => $pageToken,
                    "limit" => $limit
                ];
                $url ="https://api.partner.market.yandex.ru/campaigns/$campaignId/offers/stocks?".http_build_query($get);
    
                $tmp = ApiClient::callApi(
                    $url,
                    $headers,
                    "POST",
                    $data
                );
                
                $warehouses = $tmp["data"]["result"]["warehouses"][0]["offers"];
                $pageToken  = $tmp["data"]["result"]["paging"]["nextPageToken"];
    
                $response = array_merge($response, $warehouses);
                
            } while ($pageToken != "");
            
            return $response;
        }
        else {
            
            $response = array();
            $chunkSize = 500;
            $chunks = array_chunk($data, $chunkSize);
            
            foreach ($chunks as $index => $chunk) {
                
                $data = array(
                    "withTurnover" => false,
                    "offerIds" => $chunk
                );
                
                $tmp = ApiClient::callApi(
                    "https://api.partner.market.yandex.ru/campaigns/$campaignId/offers/stocks",
                    $headers,
                    "POST",
                    $data
                );

                $warehouses = $tmp["data"]["result"]["warehouses"][0]["offers"];
                $response = array_merge($response, $warehouses);
            }

            return $response;
    
        }
    }

    public static function updateStocks(string $campaignId, string $apiKey, array $data): array {
        $headers = [
            "Api-Key: $apiKey", 
            "Content-Type:application/json"
        ];    
        
        $chunkSize = 1000;
        $chunks = array_chunk($data, $chunkSize, true);

        $response = array();
        
        foreach ($chunks as $index => $chunk) {

            $dataSkus["skus"]=$chunk;

            $tmp = ApiClient::callApi(
                "https://api.partner.market.yandex.ru/campaigns/$campaignId/offers/stocks",
                $headers,
                "PUT",
                $dataSkus
            );

            if (isset($tmp["data"]["errors"])) 
            {
                return $tmp["data"];    
            }
            $response = $tmp;
        }
        return $response;
    }
}
?>