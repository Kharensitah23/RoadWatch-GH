<?php
/**
 * RoadWatch GH - Report Handler
 * Manages road pothole and damage reports
 */

require_once 'database.php';

class Report {
    
    /**
     * Create a new report
     */
    public static function createReport($user_id, $road_name, $region, $district, $latitude, $longitude, $description, $severity, $image_path = null) {
        global $conn;
        
        // Validate input
        if (empty($road_name) || empty($region) || empty($district) || empty($description) || empty($severity)) {
            return array('success' => false, 'message' => 'Please fill all required fields');
        }
        
        // Validate severity level
        $valid_severities = array('Low', 'Medium', 'High', 'Critical');
        if (!in_array($severity, $valid_severities)) {
            return array('success' => false, 'message' => 'Invalid severity level');
        }
        
        // Validate GPS coordinates if provided
        if (!empty($latitude) && !empty($longitude)) {
            if (!is_numeric($latitude) || !is_numeric($longitude)) {
                return array('success' => false, 'message' => 'Invalid GPS coordinates');
            }
        }
        
        // Insert report into database
        $query = "INSERT INTO reports (user_id, road_name, region, district, gps_latitude, gps_longitude, description, severity, image_path, status) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";
        $result = executeQuery($query, array($user_id, $road_name, $region, $district, $latitude, $longitude, $description, $severity, $image_path));
        
        if ($result['success']) {
            $report_id = $conn->insert_id;
            return array('success' => true, 'message' => 'Report submitted successfully', 'report_id' => $report_id);
        } else {
            return array('success' => false, 'message' => 'Failed to submit report');
        }
    }
    
    /**
     * Get report by ID
     */
    public static function getReportById($report_id) {
        $query = "SELECT r.*, u.full_name, u.email, u.phone, u.region as user_region, u.district as user_district 
                  FROM reports r 
                  LEFT JOIN users u ON r.user_id = u.id 
                  WHERE r.id = ?";
        return fetchRow($query, array($report_id));
    }
    
    /**
     * Get all reports for a user
     */
    public static function getUserReports($user_id, $limit = 50, $offset = 0) {
        $query = "SELECT * FROM reports WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?";
        return fetchData($query, array($user_id, $limit, $offset));
    }
    
    /**
     * Get all reports (for admin)
     */
    public static function getAllReports($limit = 50, $offset = 0, $filters = array()) {
        $query = "SELECT r.*, u.full_name, u.email FROM reports r 
                  LEFT JOIN users u ON r.user_id = u.id WHERE 1=1";
        $params = array();
        
        // Apply filters
        if (isset($filters['status']) && !empty($filters['status'])) {
            $query .= " AND r.status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['severity']) && !empty($filters['severity'])) {
            $query .= " AND r.severity = ?";
            $params[] = $filters['severity'];
        }
        
        if (isset($filters['region']) && !empty($filters['region'])) {
            $query .= " AND r.region = ?";
            $params[] = $filters['region'];
        }
        
        if (isset($filters['search']) && !empty($filters['search'])) {
            $query .= " AND (r.road_name LIKE ? OR r.description LIKE ? OR u.full_name LIKE ?)";
            $search = "%" . $filters['search'] . "%";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $query .= " ORDER BY r.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        return fetchData($query, $params);
    }
    
    /**
     * Update report status
     */
    public static function updateStatus($report_id, $new_status, $admin_id, $admin_notes = '') {
        global $conn;
        
        // Validate status
        $valid_statuses = array('Pending', 'Under Inspection', 'Repair Scheduled', 'Repaired', 'Rejected');
        if (!in_array($new_status, $valid_statuses)) {
            return array('success' => false, 'message' => 'Invalid status');
        }
        
        // Get current status
        $report = self::getReportById($report_id);
        if (!$report) {
            return array('success' => false, 'message' => 'Report not found');
        }
        
        $old_status = $report['status'];
        
        // Update report status
        $query = "UPDATE reports SET status = ?, admin_notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $result = executeQuery($query, array($new_status, $admin_notes, $report_id));
        
        if ($result['success']) {
            // Log status change in history
            $history_query = "INSERT INTO report_history (report_id, old_status, new_status, changed_by, comment) VALUES (?, ?, ?, ?, ?)";
            executeQuery($history_query, array($report_id, $old_status, $new_status, $admin_id, $admin_notes));
            
            return array('success' => true, 'message' => 'Report status updated successfully');
        } else {
            return array('success' => false, 'message' => 'Failed to update report status');
        }
    }
    
    /**
     * Get report statistics
     */
    public static function getStatistics() {
        $stats = array();
        
        // Total reports
        $result = fetchRow("SELECT COUNT(*) as count FROM reports");
        $stats['total_reports'] = $result['count'];
        
        // Reports by status
        $result = fetchData("SELECT status, COUNT(*) as count FROM reports GROUP BY status");
        foreach ($result as $row) {
            $status_key = strtolower(str_replace(' ', '_', $row['status']));
            $stats[$status_key] = $row['count'];
        }
        
        // Reports by severity
        $result = fetchData("SELECT severity, COUNT(*) as count FROM reports GROUP BY severity");
        foreach ($result as $row) {
            $severity_key = strtolower($row['severity']) . '_severity';
            $stats[$severity_key] = $row['count'];
        }
        
        // Reports by region
        $result = fetchData("SELECT region, COUNT(*) as count FROM reports GROUP BY region ORDER BY count DESC");
        $stats['by_region'] = $result;
        
        // Recent reports
        $result = fetchData("SELECT r.*, u.full_name FROM reports r 
                            LEFT JOIN users u ON r.user_id = u.id 
                            ORDER BY r.created_at DESC LIMIT 10");
        $stats['recent_reports'] = $result;
        
        return $stats;
    }
    
    /**
     * Get reports by region
     */
    public static function getReportsByRegion($region, $limit = 50, $offset = 0) {
        $query = "SELECT r.*, u.full_name FROM reports r 
                  LEFT JOIN users u ON r.user_id = u.id 
                  WHERE r.region = ? 
                  ORDER BY r.created_at DESC 
                  LIMIT ? OFFSET ?";
        return fetchData($query, array($region, $limit, $offset));
    }
    
    /**
     * Delete report (for admin)
     */
    public static function deleteReport($report_id) {
        global $conn;
        
        // Get report to get image path
        $report = self::getReportById($report_id);
        if (!$report) {
            return array('success' => false, 'message' => 'Report not found');
        }
        
        // Delete image if exists
        if (!empty($report['image_path']) && file_exists($report['image_path'])) {
            unlink($report['image_path']);
        }
        
        // Delete report
        $query = "DELETE FROM reports WHERE id = ?";
        $result = executeQuery($query, array($report_id));
        
        if ($result['success']) {
            return array('success' => true, 'message' => 'Report deleted successfully');
        } else {
            return array('success' => false, 'message' => 'Failed to delete report');
        }
    }
    
    /**
     * Get report history
     */
    public static function getReportHistory($report_id) {
        $query = "SELECT rh.*, u.full_name FROM report_history rh 
                  LEFT JOIN users u ON rh.changed_by = u.id 
                  WHERE rh.report_id = ? 
                  ORDER BY rh.created_at DESC";
        return fetchData($query, array($report_id));
    }
    
    /**
     * Count total reports
     */
    public static function countReports($filters = array()) {
        $query = "SELECT COUNT(*) as count FROM reports WHERE 1=1";
        $params = array();
        
        if (isset($filters['status']) && !empty($filters['status'])) {
            $query .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['region']) && !empty($filters['region'])) {
            $query .= " AND region = ?";
            $params[] = $filters['region'];
        }
        
        $result = fetchRow($query, $params);
        return $result['count'];
    }
    
    /**
     * Upload report image
     */
    public static function uploadImage($file) {
        // Validate file
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif');
        if (!in_array($file['type'], $allowed_types)) {
            return array('success' => false, 'message' => 'Only JPG, PNG, and GIF images are allowed');
        }
        
        $max_size = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $max_size) {
            return array('success' => false, 'message' => 'File size must not exceed 5MB');
        }
        
        // Create uploads directory if not exists
        $upload_dir = dirname(__DIR__) . '/uploads/reports/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $filename = uniqid('report_') . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
        $filepath = $upload_dir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return array('success' => true, 'path' => 'uploads/reports/' . $filename);
        } else {
            return array('success' => false, 'message' => 'Failed to upload image');
        }
    }
}
?>
