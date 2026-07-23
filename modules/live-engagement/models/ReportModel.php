<?php
/**
 * Live Engagement Module - Report Model
 * 
 * Manages session reports and analytics.
 * 
 * @package UNILIS\LiveEngagement\Models
 * @version 1.0.0
 */

namespace LE\Models;

class ReportModel extends BaseModel
{
    protected string $table = 'live_reports';
    
    protected array $fillable = [
        'session_id', 'report_type', 'title', 'summary_data',
        'generated_by', 'file_format', 'file_path',
    ];

    protected array $orderBy = ['created_at' => 'DESC'];

    /**
     * Generate a comprehensive report for a session
     * 
     * @param int $sessionId
     * @param int $userId
     * @return array|null
     */
    public function generateComprehensiveReport(int $sessionId, int $userId): ?array
    {
        $session = \le_get_session($sessionId);
        if (!$session) return null;

        $stats = \le_get_session_stats($sessionId);
        $participants = \le_get_participants($sessionId);
        $polls = (new PollModel())->getSessionPolls($sessionId);
        
        $reportData = [
            'session' => $session,
            'statistics' => $stats,
            'participants' => [
                'total' => count($participants),
                'list' => $participants,
            ],
            'polls' => $polls,
            'generated_at' => date('Y-m-d H:i:s'),
        ];

        $reportId = $this->create([
            'session_id' => $sessionId,
            'report_type' => 'comprehensive',
            'title' => "Report: {$session['title']}",
            'summary_data' => json_encode($reportData),
            'generated_by' => $userId,
            'file_format' => 'html',
        ]);

        if (!$reportId) return null;

        return $this->find($reportId);
    }

    /**
     * Get reports for a session
     * 
     * @param int $sessionId
     * @return array
     */
    public function getSessionReports(int $sessionId): array
    {
        return $this->findBy('session_id', $sessionId);
    }
}
