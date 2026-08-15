<?php
/**
 * Live Engagement Module - Presentation Model
 * 
 * Manages presentations, slides, and file uploads.
 * 
 * @package UNILIS\LiveEngagement\Models
 * @version 1.0.0
 */

namespace LE\Models;

/**
 * PresentationModel - Slide-based presentation management
 */
class PresentationModel extends BaseModel
{
    protected string $table = 'live_presentations';
    
    protected array $fillable = [
        'session_id', 'title', 'description', 'file_path', 'file_type',
        'file_size', 'original_filename', 'total_slides', 'current_slide',
        'is_active', 'allow_download', 'allow_annotations', 'presenter_notes',
        'created_by',
    ];

    protected array $orderBy = ['created_at' => 'DESC'];

    /**
     * Create a presentation and its slides
     * 
     * @param array $data Presentation data
     * @param array $slides Slide data
     * @return int|false
     */
    public function createWithSlides(array $data, array $slides = [])
    {
        $this->db->beginTransaction();
        
        $data['total_slides'] = count($slides);
        $presId = $this->create($data);
        
        if (!$presId) {
            $this->db->rollback();
            return false;
        }

        if (!empty($slides)) {
            $slideModel = new SlideModel();
            foreach ($slides as $index => $slide) {
                $slide['presentation_id'] = $presId;
                $slide['slide_number'] = $index + 1;
                if (!$slideModel->create($slide)) {
                    $this->db->rollback();
                    return false;
                }
            }
        }
        
        $this->db->commit();
        return $presId;
    }

    /**
     * Get slides for a presentation
     * 
     * @param int $presentationId
     * @return array
     */
    public function getSlides(int $presentationId): array
    {
        $slideModel = new SlideModel();
        return $slideModel->findBy('presentation_id', $presentationId);
    }

    /**
     * Navigate to a specific slide
     * 
     * @param int $presentationId
     * @param int $slideNumber
     * @return bool
     */
    public function goToSlide(int $presentationId, int $slideNumber): bool
    {
        $pres = $this->find($presentationId);
        if (!$pres) return false;

        $slideNumber = max(0, min($slideNumber, $pres['total_slides']));
        return $this->update($presentationId, ['current_slide' => $slideNumber]) !== false;
    }

    /**
     * Get active presentations for a session
     * 
     * @param int $sessionId
     * @return array|null
     */
    public function getActivePresentation(int $sessionId): ?array
    {
        $pres = $this->db->fetchOne(
            "SELECT * FROM live_presentations WHERE session_id = ? AND is_active = 1 LIMIT 1",
            [$sessionId],
            'i'
        );
        
        if ($pres) {
            $pres['slides'] = $this->getSlides($pres['id']);
        }
        
        return $pres;
    }

    /**
     * Get a lecturer's presentation library.
     *
     * Presentations belong to a live session, so ownership and course data
     * are derived from the associated session rather than duplicated on the
     * presentation record.
     *
     * @param int $userId Lecturer or administrator ID
     * @param string $search Title, description, or original filename search
     * @param int $courseId Optional course filter
     * @param string $sort Sort option from the library UI
     * @param int $page One-based page number
     * @param int $perPage Number of records per page
     * @return array
     */
    public function getUserPresentations(
        int $userId,
        string $search = '',
        int $courseId = 0,
        string $sort = 'newest',
        int $page = 1,
        int $perPage = 20,
        int $sessionId = 0
    ): array {
        $where = ['s.lecturer_id = ?'];
        $params = [$userId];
        $types = 'i';

        if ($sessionId > 0) {
            $where[] = 'p.session_id = ?';
            $params[] = $sessionId;
            $types .= 'i';
        }

        if ($search !== '') {
            $where[] = '(p.title LIKE ? OR p.description LIKE ? OR p.original_filename LIKE ?)';
            $term = '%' . $search . '%';
            array_push($params, $term, $term, $term);
            $types .= 'sss';
        }

        if ($courseId > 0) {
            $where[] = 's.course_id = ?';
            $params[] = $courseId;
            $types .= 'i';
        }

        $orderBy = [
            'oldest' => 'p.created_at ASC',
            'name' => 'p.title ASC',
            // View tracking is not yet stored for presentations; newest is
            // the stable fallback until that feature is added to the schema.
            'views' => 'p.created_at DESC',
            'newest' => 'p.created_at DESC',
        ][$sort] ?? 'p.created_at DESC';

        $page = max(1, $page);
        $perPage = max(1, min($perPage, 100));
        $offset = ($page - 1) * $perPage;
        $whereSql = implode(' AND ', $where);

        $sql = "SELECT p.*, s.course_id, c.name AS course_name,
                       0 AS views, 1 AS version, 'private' AS visibility,
                       NULL AS thumbnail_path
                FROM live_presentations p
                INNER JOIN live_sessions s ON s.id = p.session_id
                LEFT JOIN courses c ON c.id = s.course_id
                WHERE {$whereSql}
                ORDER BY {$orderBy}
                LIMIT ? OFFSET ?";

        $params[] = $perPage;
        $params[] = $offset;
        $types .= 'ii';

        return $this->db->select($sql, $params, $types) ?? [];
    }

    /**
     * Count a lecturer's presentations using the same filters as the library.
     */
    public function countUserPresentations(int $userId, string $search = '', int $courseId = 0, int $sessionId = 0): int
    {
        $where = ['s.lecturer_id = ?'];
        $params = [$userId];
        $types = 'i';

        if ($sessionId > 0) {
            $where[] = 'p.session_id = ?';
            $params[] = $sessionId;
            $types .= 'i';
        }

        if ($search !== '') {
            $where[] = '(p.title LIKE ? OR p.description LIKE ? OR p.original_filename LIKE ?)';
            $term = '%' . $search . '%';
            array_push($params, $term, $term, $term);
            $types .= 'sss';
        }

        if ($courseId > 0) {
            $where[] = 's.course_id = ?';
            $params[] = $courseId;
            $types .= 'i';
        }

        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total
             FROM live_presentations p
             INNER JOIN live_sessions s ON s.id = p.session_id
             WHERE ' . implode(' AND ', $where),
            $params,
            $types
        );

        return (int) ($row['total'] ?? 0);
    }
}
