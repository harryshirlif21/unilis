<?php
require_once __DIR__.'/../models/DashboardModel.php';
require_once __DIR__.'/../auth/Auth.php';
require_once __DIR__.'/../utils/helpers.php';

class DashboardController {
    private DashboardModel $model;
    
    public function __construct() { 
        $this->model = new DashboardModel(); 
    }

    public function index($param = null) {
        Auth::guard();
        
        $userRole = Auth::role();
        $userId = Auth::id();
        
        // Get role-specific dashboard data
        $userDashboard = $this->model->getUserDashboard($userId, $userRole);
        
        // Get general system data
        $data = [
            'stats'                => $this->model->getStats(),
            'labs'                 => $this->model->getLabOccupancy(),
            'schedule'             => $this->model->getTodaySchedule(),
            'activity'             => $this->model->getRecentActivity(),
            'blocks'               => $this->model->getRecentBlocks(),
            'weekly_activity'      => $this->model->getWeeklyActivity(),
            'lab_utilization'     => $this->model->getLabUtilizationData(),
            'asset_breakdown'      => $this->model->getAssetStatusBreakdown(),
            'top_users'            => $this->model->getTopUsers(),
            'practical_completion' => $this->model->getPracticalCompletionRates(),
            'system_health'        => $this->model->getSystemHealth(),
            'user_name'           => Auth::name(),
            'user_role'           => $userRole,
            'user_dashboard'       => $userDashboard
        ];
        
        renderView('dashboard/index', $data);
    }
    
    public function apiStats($param = null) {
        Auth::guard();
        
        $stats = $this->model->getStats();
        jsonResponse($stats);
    }
    
    public function apiActivity($param = null) {
        Auth::guard();
        
        $activity = $this->model->getRecentActivity();
        jsonResponse($activity);
    }
    
    public function apiLabOccupancy($param = null) {
        Auth::guard();
        
        $occupancy = $this->model->getLabOccupancy();
        jsonResponse($occupancy);
    }
    
    public function apiSystemHealth($param = null) {
        Auth::guard();
        
        $health = $this->model->getSystemHealth();
        jsonResponse($health);
    }
}
