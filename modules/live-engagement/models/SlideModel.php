<?php
/**
 * Live Engagement Module - Slide Model
 * 
 * Manages individual presentation slides.
 * 
 * @package UNILIS\LiveEngagement\Models
 * @version 1.0.0
 */

namespace LE\Models;

class SlideModel extends BaseModel
{
    protected string $table = 'presentation_slides';
    
    protected array $fillable = [
        'presentation_id', 'slide_number', 'image_path', 'content_html',
        'notes', 'duration_seconds', 'transition_type',
    ];

    protected array $orderBy = ['slide_number' => 'ASC'];
}
