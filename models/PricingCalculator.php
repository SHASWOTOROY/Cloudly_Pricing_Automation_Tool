<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/PricingModel.php';

class PricingCalculator {
    private $conn;
    private $pricingModel;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
        $this->pricingModel = new PricingModel();
    }
    
    // VPC Pricing
    public function calculateVPC($config) {
        $cost = 0;
        $region = $config['region'] ?? '';
        if (empty($region)) return 0;
        
        // NAT Gateway pricing
        $nat_price = $this->pricingModel->getVPCPrice('nat_gateway', $region);
        $cost += $config['nat_gateway_count'] * $nat_price['price_per_hour'] * 730;
        
        // VPC Endpoint pricing
        $endpoint_price = $this->pricingModel->getVPCPrice('vpc_endpoint', $region);
        $cost += $config['vpc_endpoint_count'] * $endpoint_price['price_per_hour'] * 730;
        
        // Data transfer pricing
        $data_transfer_price = $this->pricingModel->getVPCPrice('data_transfer', $region);
        $cost += $config['data_transfer_gb'] * $data_transfer_price['price_per_gb'];
        
        return $cost;
    }
    
    // S3 Pricing
    public function calculateS3($config) {
        $cost = 0;
        $region = $config['region'] ?? '';
        if (empty($region)) return 0;
        $storage_class = $config['storage_class'] ?? 'standard';
        
        $s3_price = $this->pricingModel->getS3Price($storage_class, $region);
        
        $cost += $config['storage_gb'] * $s3_price['price_per_gb'];
        $cost += $config['requests_million'] * $s3_price['request_price'];
        $cost += $config['data_transfer_gb'] * $s3_price['data_transfer_price'];
        
        return $cost;
    }
    
    // RDS Pricing
    public function calculateRDS($config) {
        $cost = 0;
        $region = $config['region'] ?? '';
        if (empty($region)) return 0;
        $engine = $config['engine'] ?? 'mysql';
        
        // Instance pricing
        $instance_price_per_hour = $this->pricingModel->getRDSInstancePrice($config['instance_type'], $engine, $region);
        $instance_cost = $instance_price_per_hour * 730;
        $cost += $instance_cost * $config['quantity'];
        
        if ($config['multi_az']) {
            $cost *= 2; // Multi-AZ doubles the cost
        }
        
        // Storage cost
        $storage_price = $this->pricingModel->getRDSStoragePrice($config['storage_type'], $region);
        $cost += $config['storage_gb'] * $storage_price['price_per_gb'];
        
        return $cost;
    }
    
    // EKS Pricing
    public function calculateEKS($config) {
        $cost = 0;
        $region = $config['region'] ?? '';
        if (empty($region)) return 0;
        
        $cluster_price_per_hour = $this->pricingModel->getEKSPrice('cluster', $region);
        $cost += $config['cluster_count'] * $cluster_price_per_hour * 730;
        
        // Node group pricing (EC2 instances) - only if instance_type is provided
        if (!empty($config['instance_type'])) {
            $node_price_per_hour = $this->pricingModel->getEC2Price($config['instance_type'], $region);
            $node_cost = $node_price_per_hour * 730;
            $cost += $node_cost * $config['node_count'] * $config['node_group_count'];
        }
        
        return $cost;
    }
    
    // ECR Pricing
    public function calculateECR($config) {
        $cost = 0;
        $region = $config['region'] ?? '';
        if (empty($region)) return 0;
        
        $ecr_price = $this->pricingModel->getECRPrice('storage', $region);
        $cost += $config['storage_gb'] * $ecr_price['price_per_gb_per_month'];
        
        $data_transfer_price = $this->pricingModel->getECRPrice('data_transfer', $region);
        $cost += $config['data_transfer_gb'] * $data_transfer_price['price_per_gb'];
        
        return $cost;
    }
    
    // Load Balancer Pricing
    public function calculateLoadBalancer($config) {
        $cost = 0;
        $region = $config['region'] ?? '';
        if (empty($region)) return 0;
        
        $lb_price = $this->pricingModel->getLoadBalancerPrice($config['load_balancer_type'], $region);
        $cost += $lb_price['price_per_hour'] * 730 * $config['quantity'];
        $cost += $config['data_processed_gb'] * $lb_price['price_per_gb'];
        
        return $cost;
    }
    
    // WAF Pricing
    public function calculateWAF($config) {
        $cost = 0;
        $region = $config['region'] ?? '';
        if (empty($region)) return 0;
        
        $web_acl_price = $this->pricingModel->getWAFPrice('web_acl', $region);
        $cost += $config['web_acl_count'] * $web_acl_price['price'];
        
        $rule_price = $this->pricingModel->getWAFPrice('rule', $region);
        $cost += $config['rules_count'] * $rule_price['price'];
        
        $request_price = $this->pricingModel->getWAFPrice('request', $region);
        $cost += $config['requests_million'] * $request_price['price'];
        
        return $cost;
    }
    
    // Route53 Pricing
    public function calculateRoute53($config) {
        $cost = 0;
        $region = $config['region'] ?? '';
        if (empty($region)) return 0;
        
        $hosted_zone_price = $this->pricingModel->getRoute53Price('hosted_zone', $region);
        $cost += $config['hosted_zones'] * $hosted_zone_price['price'];
        
        $query_price = $this->pricingModel->getRoute53Price('query', $region);
        $cost += $config['queries_million'] * $query_price['price'];
        
        $health_check_price = $this->pricingModel->getRoute53Price('health_check', $region);
        $cost += $config['health_checks'] * $health_check_price['price'];
        
        return $cost;
    }
    
    // EBS Pricing
    public function calculateEBS($config) {
        $cost = 0;
        $region = $config['region'] ?? '';
        if (empty($region)) return 0;
        
        $ebs_price = $this->pricingModel->getEBSPrice($config['volume_type'], $region);
        $cost += $config['size_gb'] * $ebs_price['price_per_gb'];
        
        if ($config['volume_type'] == 'io1' || $config['volume_type'] == 'io2') {
            $iops = intval($config['iops'] ?? 3000);
            if ($iops > 3000) {
                $cost += ($iops - 3000) * $ebs_price['iops_price'] / 1000;
            }
        }
        
        if ($config['volume_type'] == 'gp3') {
            $throughput = intval($config['throughput'] ?? 125);
            if ($throughput > 125) {
                $cost += max(0, ($throughput - 125) * $ebs_price['throughput_price'] / 1000);
            }
        }
        
        return $cost;
    }
}






