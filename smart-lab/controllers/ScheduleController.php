<?php
require_once __DIR__.'/../models/ScheduleModel.php';
require_once __DIR__.'/../auth/Auth.php';
require_once __DIR__.'/../utils/helpers.php';

class ScheduleController {
    private ScheduleModel $model;
    
    public function __construct() {
        $this->model = new ScheduleModel();
    }
    
    public function index($param = null) {
        Auth::guard();
        
        $currentDate = $_GET['date'] ?? date('Y-m-d');
        $labFilter = $_GET['lab'] ?? '';
        
        $todaySchedule = $this->model->getTodaySchedule();
        $weekSchedule = $this->model->getWeekSchedule();
        $monthSchedule = $this->model->getMonthSchedule();
        $allSchedule = $this->model->getAllSchedule();
        $stats = $this->model->getScheduleStats();
        $labs = $this->model->getLabs();
        
        renderView('schedule/index', [
            'todaySchedule' => $todaySchedule,
            'weekSchedule' => $weekSchedule,
            'monthSchedule' => $monthSchedule,
            'allSchedule' => $allSchedule,
            'stats' => $stats,
            'labs' => $labs,
            'currentDate' => $currentDate
        ]);
    }
}
