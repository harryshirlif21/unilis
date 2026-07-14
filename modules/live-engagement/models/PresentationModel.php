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
}

/**
 * SlideModel - Individual slide management
 */
class SlideModel extends BaseModel
{
    protected string $table = 'presentation_slides';
    
    protected array $fillable = [
        'presentation_id', 'slide_number', 'image_path', 'content_html',
        'notes', 'duration_seconds', 'transition_type',
    ];

    protected array $orderBy = ['slide_number' => 'ASC'];
}

/**
 * ParticipantModel - Manages session participants
 */
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