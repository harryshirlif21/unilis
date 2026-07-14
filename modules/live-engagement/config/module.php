<?php
/**
 * Live Engagement Module - Configuration
 * 
 * Central configuration for the Live Engagement Module.
 * 
 * @package UNILIS\LiveEngagement
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('UNILIS_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * Live Engagement Module Configuration
 */
return [
    // Module metadata
    'module' => [
        'name' => 'Live Engagement',
        'version' => '1.0.0',
        'description' => 'Interactive live engagement module for presentations, polls, quizzes, whiteboards, and more.',
        'author' => 'UNILIS Team',
        'min_php_version' => '8.0',
        'dependencies' => ['Authentication', 'Courses'],
    ],

    // Session configuration
    'session' => [
        'code_length' => 8,
        'code_characters' => 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789',
        'default_duration' => 60,
        'max_duration' => 480,
        'auto_close_minutes' => 5,
    ],

    // Poll defaults
    'polls' => [
        'default_time_limit' => 120,
        'max_options' => 26,
        'min_options' => 2,
        'rating_max' => 10,
        'rating_min' => 1,
        'likert_scale' => [
            'Strongly Disagree',
            'Disagree',
            'Neutral',
            'Agree',
            'Strongly Agree',
        ],
    ],

    // Quiz defaults
    'quizzes' => [
        'default_time_limit' => 10,
        'max_time_limit' => 120,
        'max_questions' => 100,
        'max_attempts' => 3,
        'passing_score' => 50,
    ],

    // Presentation defaults
    'presentations' => [
        'max_file_size' => 52428800, // 50MB
        'allowed_types' => ['pdf', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'mp4', 'webm', 'html'],
        'max_slides' => 200,
        'slide_width' => 1920,
        'slide_height' => 1080,
    ],

    // Whiteboard defaults
    'whiteboard' => [
        'width' => 1920,
        'height' => 1080,
        'max_objects' => 5000,
        'stroke_colors' => ['#000000', '#FF0000', '#00FF00', '#0000FF', '#FFFF00', '#FF00FF', '#00FFFF', '#FFFFFF'],
        'stroke_widths' => [1, 2, 3, 5, 8, 12],
        'font_sizes' => [12, 14, 16, 18, 24, 32, 48, 64],
    ],

    // Word cloud defaults
    'wordcloud' => [
        'max_words' => 100,
        'min_word_length' => 2,
        'max_word_length' => 50,
        'default_stop_words' => [
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to',
            'for', 'of', 'by', 'with', 'is', 'are', 'was', 'were', 'be',
            'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did',
            'will', 'would', 'can', 'could', 'shall', 'should', 'may',
            'might', 'this', 'that', 'these', 'those', 'it', 'its',
        ],
    ],

    // Upload paths
    'uploads' => [
        'presentations' => __DIR__ . '/../uploads/presentations/',
        'temp' => __DIR__ . '/../uploads/temp/',
        'max_file_size' => 52428800, // 50MB
        'allowed_mime_types' => [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'image/jpeg',
            'image/png',
            'image/gif',
            'video/mp4',
            'video/webm',
            'text/html',
        ],
    ],

    // Security
    'security' => [
        'csrf_token_name' => 'le_csrf_token',
        'session_key_prefix' => 'le_',
        'rate_limit' => [
            'polls' => 5,  // max polls per minute
            'responses' => 30, // max responses per minute
            'reactions' => 20, // max reactions per minute
        ],
    ],

    // Real-time configuration
    'realtime' => [
        'polling_interval' => 3000, // ms between polling requests
        'max_reconnect_attempts' => 5,
        'reconnect_delay' => 2000, // ms
    ],

    // Theme - UNILIS 2025/2026 Premium Design System
    'theme' => [
        'design_system' => 'Modern Glassmorphism',
        'theme_switching' => true,
        'dark_mode' => true,
        'light_mode' => true,
        'high_contrast_mode' => true,
        'colors' => [
            'primary' => '#1B5E20',
            'primary_light' => '#2E7D32',
            'primary_dark' => '#0D3B0E',
            'secondary' => '#F9A825',
            'secondary_light' => '#FDD835',
            'secondary_dark' => '#F57F17',
            'accent' => '#EF6C00',
            'accent_light' => '#FF8F00',
            'accent_dark' => '#E65100',
            'success' => '#16A34A',
            'success_light' => '#22C55E',
            'warning' => '#F59E0B',
            'warning_light' => '#FBBF24',
            'danger' => '#DC2626',
            'danger_light' => '#EF4444',
            'info' => '#2563EB',
            'info_light' => '#3B82F6',
        ],
        'glassmorphism' => [
            'background' => 'rgba(27, 94, 32, 0.08)',
            'background_dark' => 'rgba(27, 94, 32, 0.15)',
            'border' => 'rgba(255, 255, 255, 0.18)',
            'border_dark' => 'rgba(255, 255, 255, 0.08)',
            'shadow' => '0 8px 32px 0 rgba(27, 94, 32, 0.15)',
            'backdrop_filter' => 'blur(12px)',
            'backdrop_filter_heavy' => 'blur(24px)',
        ],
        'typography' => [
            'font_family' => "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
            'heading_weight' => '700',
            'body_weight' => '400',
            'modern_spacing' => true,
            'clear_hierarchy' => true,
        ],
        'spacing' => [
            'unit' => 8,
            'xs' => '8px',
            'sm' => '16px',
            'md' => '24px',
            'lg' => '32px',
            'xl' => '48px',
            '2xl' => '64px',
            '3xl' => '80px',
        ],
        'radius' => [
            'sm' => '8px',
            'md' => '12px',
            'lg' => '16px',
            'xl' => '20px',
            '2xl' => '24px',
            'full' => '9999px',
        ],
        'shadows' => [
            'sm' => '0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04)',
            'md' => '0 4px 6px rgba(0,0,0,0.07), 0 2px 4px rgba(0,0,0,0.06)',
            'lg' => '0 10px 15px rgba(0,0,0,0.1), 0 4px 6px rgba(0,0,0,0.05)',
            'xl' => '0 20px 25px rgba(0,0,0,0.1), 0 10px 10px rgba(0,0,0,0.04)',
            '2xl' => '0 25px 50px rgba(0,0,0,0.15)',
            'inner' => 'inset 0 2px 4px rgba(0,0,0,0.06)',
            'glass' => '0 8px 32px 0 rgba(27, 94, 32, 0.12)',
            'glass_hover' => '0 12px 40px 0 rgba(27, 94, 32, 0.2)',
        ],
        'transitions' => [
            'fast' => '150ms cubic-bezier(0.4, 0, 0.2, 1)',
            'normal' => '250ms cubic-bezier(0.4, 0, 0.2, 1)',
            'slow' => '350ms cubic-bezier(0.4, 0, 0.2, 1)',
            'spring' => '500ms cubic-bezier(0.68, -0.55, 0.265, 1.55)',
        ],
        'animations' => [
            'page_transitions' => true,
            'component_transitions' => true,
            'modal_animations' => true,
            'button_feedback' => true,
            'card_hover' => true,
            'smooth_scroll' => true,
            'animated_charts' => true,
            'loading_skeletons' => true,
            'ripple_effect' => true,
        ],
        'icons' => [
            'library' => 'Material Symbols Rounded',
            'animated' => true,
            'consistent' => true,
        ],
        'accessibility' => [
            'keyboard_navigation' => true,
            'screen_reader_support' => true,
            'wcag_compliance' => 'AA',
            'high_contrast' => true,
            'focus_indicators' => true,
        ],
    ],
];