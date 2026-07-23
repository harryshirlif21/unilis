<?php
/**
 * Live Engagement Module - Whiteboard Model
 * 
 * Manages whiteboard canvases.
 * 
 * @package UNILIS\LiveEngagement\Models
 * @version 1.0.0
 */

namespace LE\Models;

class WhiteboardModel extends BaseModel
{
    protected string $table = 'live_whiteboards';
    
    protected array $fillable = [
        'session_id', 'title', 'width', 'height', 'background_color',
        'is_active', 'is_collaborative', 'created_by',
    ];

    protected array $orderBy = ['created_at' => 'DESC'];

    /**
     * Add an object to the whiteboard
     * 
     * @param int $whiteboardId
     * @param string $objectType
     * @param array $objectData
     * @param array $styleData
     * @param int|null $userId
     * @return int|false
     */
    public function addObject(int $whiteboardId, string $objectType, array $objectData, 
                               array $styleData = [], ?int $userId = null)
    {
        $maxZ = $this->db->fetchOne(
            "SELECT MAX(z_index) as max_z FROM whiteboard_objects WHERE whiteboard_id = ?",
            [$whiteboardId],
            'i'
        );

        return $this->db->insert(
            "INSERT INTO whiteboard_objects (whiteboard_id, object_type, object_data, style_data, z_index, created_by) 
             VALUES (?, ?, ?, ?, ?, ?)",
            [$whiteboardId, $objectType, json_encode($objectData), json_encode($styleData), 
             ($maxZ['max_z'] ?? 0) + 1, $userId],
            'isssii'
        );
    }

    /**
     * Get all objects for a whiteboard
     * 
     * @param int $whiteboardId
     * @return array
     */
    public function getObjects(int $whiteboardId): array
    {
        return $this->db->select(
            "SELECT * FROM whiteboard_objects WHERE whiteboard_id = ? ORDER BY z_index ASC",
            [$whiteboardId],
            'i'
        ) ?? [];
    }

    /**
     * Clear all objects from a whiteboard
     * 
     * @param int $whiteboardId
     * @return bool
     */
    public function clearObjects(int $whiteboardId): bool
    {
        return $this->db->delete(
            "DELETE FROM whiteboard_objects WHERE whiteboard_id = ?",
            [$whiteboardId],
            'i'
        ) !== false;
    }
}
