<?php
/**
 * Live Engagement Module - Participant Model
 * 
 * Manages session participants.
 * 
 * @package UNILIS\LiveEngagement\Models
 * @version 1.0.0
 */

namespace LE\Models;

class ParticipantModel extends BaseModel
{
    protected string $table = 'live_participants';
    
    protected array $fillable = [
        'session_id', 'user_id', 'display_name', 'email', 'role',
        'joined_at', 'left_at', 'duration_seconds', 'is_online',
        'hand_raised', 'hand_raised_at', 'reaction', 'device_info',
        'ip_address', 'attendance_recorded',
    ];

    protected array $orderBy = ['joined_at' => 'ASC'];
}
