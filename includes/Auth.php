<?php
/**
 * RoadWatch GH - Authentication Handler
 * Manages user registration, login, and session management
 */

session_start();
require_once 'database.php';

class Auth {
    
    /**
     * Register a new user
     */
    public static function register($full_name, $email, $password, $phone, $region, $district) {
        global $conn;
        
        // Validate input
        if (empty($full_name) || empty($email) || empty($password)) {
            return array('success' => false, 'message' => 'Please fill all required fields');
        }
        
        // Check if email already exists
        $check_email = fetchRow("SELECT id FROM users WHERE email = ?", array($email));
        if ($check_email) {
            return array('success' => false, 'message' => 'Email already registered');
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return array('success' => false, 'message' => 'Invalid email format');
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        
        // Insert user into database
        $query = "INSERT INTO users (full_name, email, password, phone, region, district) VALUES (?, ?, ?, ?, ?, ?)";
        $result = executeQuery($query, array($full_name, $email, $hashed_password, $phone, $region, $district));
        
        if ($result['success']) {
            return array('success' => true, 'message' => 'Registration successful. Please login.');
        } else {
            return array('success' => false, 'message' => 'Registration failed. Please try again.');
        }
    }
    
    /**
     * Login user
     */
    public static function login($email, $password) {
        global $conn;
        
        // Validate input
        if (empty($email) || empty($password)) {
            return array('success' => false, 'message' => 'Please enter email and password');
        }
        
        // Fetch user from database
        $user = fetchRow("SELECT * FROM users WHERE email = ? AND status = 'active'", array($email));
        
        if (!$user) {
            return array('success' => false, 'message' => 'Invalid email or password');
        }
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            return array('success' => false, 'message' => 'Invalid email or password');
        }
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_time'] = time();
        
        return array('success' => true, 'message' => 'Login successful', 'role' => $user['role']);
    }
    
    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Check if user is admin
     */
    public static function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
    
    /**
     * Get current user ID
     */
    public static function getUserId() {
        return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    }
    
    /**
     * Get current user data
     */
    public static function getCurrentUser() {
        if (!self::isLoggedIn()) {
            return null;
        }
        
        return fetchRow("SELECT * FROM users WHERE id = ?", array(self::getUserId()));
    }
    
    /**
     * Logout user
     */
    public static function logout() {
        session_unset();
        session_destroy();
        return array('success' => true, 'message' => 'Logout successful');
    }
    
    /**
     * Change password
     */
    public static function changePassword($user_id, $old_password, $new_password, $confirm_password) {
        global $conn;
        
        // Validate input
        if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
            return array('success' => false, 'message' => 'Please fill all password fields');
        }
        
        // Check if new passwords match
        if ($new_password !== $confirm_password) {
            return array('success' => false, 'message' => 'New passwords do not match');
        }
        
        // Fetch user
        $user = fetchRow("SELECT * FROM users WHERE id = ?", array($user_id));
        
        if (!$user) {
            return array('success' => false, 'message' => 'User not found');
        }
        
        // Verify old password
        if (!password_verify($old_password, $user['password'])) {
            return array('success' => false, 'message' => 'Old password is incorrect');
        }
        
        // Hash new password
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
        
        // Update password
        $query = "UPDATE users SET password = ? WHERE id = ?";
        $result = executeQuery($query, array($hashed_password, $user_id));
        
        if ($result['success']) {
            return array('success' => true, 'message' => 'Password changed successfully');
        } else {
            return array('success' => false, 'message' => 'Failed to change password');
        }
    }
    
    /**
     * Update user profile
     */
    public static function updateProfile($user_id, $full_name, $phone, $region, $district) {
        global $conn;
        
        $query = "UPDATE users SET full_name = ?, phone = ?, region = ?, district = ? WHERE id = ?";
        $result = executeQuery($query, array($full_name, $phone, $region, $district, $user_id));
        
        if ($result['success']) {
            // Update session variables
            $_SESSION['full_name'] = $full_name;
            return array('success' => true, 'message' => 'Profile updated successfully');
        } else {
            return array('success' => false, 'message' => 'Failed to update profile');
        }
    }
}

// Redirect to login if not logged in (for protected pages)
function requireLogin() {
    if (!Auth::isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// Redirect to login if not admin (for admin pages)
function requireAdmin() {
    if (!Auth::isLoggedIn() || !Auth::isAdmin()) {
        header('Location: login.php');
        exit;
    }
}
?>
