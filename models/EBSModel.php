<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/PricingCalculator.php';

class EBSModel {
    private $conn;
    private $calculator;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
        $this->calculator = new PricingCalculator();
    }
    
    public function saveVolumes($project_id, $volumes) {
        // Get project region
        $stmt = $this->conn->prepare("SELECT region FROM projects WHERE id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $project = $result->fetch_assoc();
        $project_region = $project['region'] ?? '';
        
        // Delete existing volumes
        $stmt = $this->conn->prepare("DELETE FROM ebs_volumes WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        
        foreach ($volumes as $volume) {
            // Ensure region is set from project
            if (empty($volume['region'])) {
                $volume['region'] = $project_region;
            }
            $unit_cost = $this->calculator->calculateEBS($volume);
            $total_cost = $unit_cost;
            
            $stmt = $this->conn->prepare("INSERT INTO ebs_volumes (project_id, ec2_instance_id, server_type, server_name, volume_type, size_gb, iops, throughput, unit_cost, total_cost) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ec2_id = $volume['ec2_instance_id'] ?? null;
            $stmt->bind_param("iissiiidd", $project_id, $ec2_id, $volume['server_type'], $volume['server_name'], $volume['volume_type'], $volume['size_gb'], $volume['iops'], $volume['throughput'], $unit_cost, $total_cost);
            $stmt->execute();
        }
        
        return ['success' => true];
    }
    
    public function getVolumes($project_id) {
        $stmt = $this->conn->prepare("SELECT * FROM ebs_volumes WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $volumes = [];
        
        while ($row = $result->fetch_assoc()) {
            $volumes[] = [
                'id' => $row['id'],
                'ec2_instance_id' => $row['ec2_instance_id'],
                'server_type' => $row['server_type'],
                'server_name' => $row['server_name'],
                'volume_type' => $row['volume_type'],
                'size_gb' => $row['size_gb'],
                'iops' => $row['iops'],
                'throughput' => $row['throughput'],
                'unit_cost' => floatval($row['unit_cost']),
                'total_cost' => floatval($row['total_cost'])
            ];
        }
        
        return $volumes;
    }
    
    public function getEC2InstancesForProject($project_id) {
        $stmt = $this->conn->prepare("SELECT id, instance_type, quantity FROM ec2_instances WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $instances = [];
        
        while ($row = $result->fetch_assoc()) {
            $instances[] = [
                'id' => $row['id'],
                'label' => $row['instance_type'] . ' (x' . $row['quantity'] . ')'
            ];
        }
        
        return $instances;
    }
}

